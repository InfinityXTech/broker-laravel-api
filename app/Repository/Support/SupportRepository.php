<?php

namespace App\Repository\Support;

use App\Models\User;
use App\Models\TechSupport;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Classes\NotificationReporter;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\SlackNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Models\TechSupport\TechSupportComment;
use App\Repository\Support\ISupportRepository;
use App\Notifications\Slack\Messages\SlackMessage;
use Exception;
use Illuminate\Support\Facades\Log;

class SupportRepository extends BaseRepository implements ISupportRepository
{
	/**
	 * @var Model
	 */
	protected $model;

	/**
	 * BaseRepository constructor.
	 *
	 * @param Model $model
	 */
	public function __construct(TechSupport $model)
	{
		$this->model = $model;
	}

	public function index(array $columns = ['*'], array $relations = []): Collection
	{
		$items = $this->model->with($relations)->get($columns)
			->sortByDesc(function ($item, $key) {
				$a = (array)($item->timestamp ?? []);
				return intval($a['milliseconds'] ?? 0);
			})->values();
		return $items;
	}

	public function page(int $page, array $payload, array $columns = ['*'], array $relations = []): Collection
	{

		// $total = $this->model->all()->count();
		$user_id = Auth::id();

		$query = $this->model->with($relations);
		// ->leftJoin('users', 'integration.created_by', '=', 'users._id');

		if (!empty($payload['status'])) {
			if (is_array($payload['status'])) {
				foreach ($payload['status'] as $index => &$status) {
					$multiple = explode(',', $status);
					if (count($multiple) > 1) {
						unset($payload['status'][$index]);
						foreach ($multiple as $stat) {
							$payload['status'][] = (int) $stat;
						}
					} else {
						$status = (int)$status;
					}
				}
				Log::warning('SASA - TEST', $payload['status']);
			} else if ((int)$payload['status'] > 0) {
				$query = $query->whereIn('status', [(int)$payload['status'], (string)$payload['status']]); //->orWhere('status', '=', (string)$payload['status']);
			}
		}

		if (!empty($payload['broker'])) {
			$query = $query->where('broker', '=', $payload['broker']);
		}

		if (!empty($payload['traffic_endpoint'])) {
			$query = $query->where('traffic_endpoint', '=', $payload['traffic_endpoint']);
		}

		if (!empty($payload['search'])) {
			$search = '%' . $payload['search'] . '%';
			$query = $query->where(function ($q) use ($search) {
				$q->orWhere('ticket_number', 'like', $search)->orWhere('note', 'like', $search);
			});
			// ->where('users.account_email', 'like', $search);
			// ->orWhere('users.name', 'like', $search);

			foreach (TechSupport::types as $type => $title) {
				if (preg_match('/.*' . $payload['search'] . '.*/i', $title)) {
					$query = $query->orWhere('type', '=', $type);
					break;
				}
			}
		}

		if (!empty($payload['timeframe'])) {
			$string = $payload['timeframe'];
			$explode = explode(' - ', $string);

			$start = strtotime($explode[0] . " 00:00:00");
			$end = strtotime($explode[1] . " 23:59:59");

			$start = new \MongoDB\BSON\UTCDateTime($start * 1000);
			$end = new \MongoDB\BSON\UTCDateTime($end * 1000);

			$query = $query->where(function ($q) use ($start, $end) {
				$q->orWhere(function ($q2) use ($start, $end) {
					$q2->where('timestamp', '>=', $start)->where('timestamp', '<=', $end);
				});
				$q->orWhere(function ($q2) use ($start, $end) {
					$q2->where('finished.timestamp', '>=', $start)->where('finished.timestamp', '<=', $end);
				});
			});
		}

		if (!empty($payload['user'])) {
			$query = $query->where(function ($q) use ($payload) {
				$q
					->orWhere('created_by', '=', $payload['user'])
					->orWhere('assigned_to', '=', $payload['user'])
					->orWhere('taken_to_work.action_by', '=', $payload['user'])
					->orWhere('finished.action_by', '=', $payload['user']);
			});
		}

		// if (!Gate::allows('role:admin')) {
		if (Gate::allows('support[is_only_assigned=1]')) {
			$query = $query->where(function ($q) use ($user_id) {
				// $q->whereRaw(['assigned_to' => ['$exists' => false]]);
				// $q->orWhere('assigned_to', '=', null)->orWhere('assigned_to', '=', '')->orWhere('assigned_to', '=', $user_id);
				$q->orWhere('created_by', '=', $user_id)->orWhere('assigned_to', '=', $user_id);
			});
		}

		// GeneralHelper::PrintR([$query->toSql()]);die();

		$query = $query->get($columns);

		$total = $query->count();

		$query = $query->sortByDesc(function ($item, $key) {
			$a = (array)($item->timestamp ?? []);
			return intval($a['milliseconds'] ?? 0);
		});

		$items = $query->skip(($page - 1) * 10)
			->take(10)
			->values();

		foreach ($items as &$item) {
			$start = GeneralHelper::GeDateFromTimestamp($item['timestamp']);
			$taken_to_work = $start;
			$finished = time();
			$status = ((int)($item['status'] ?? 0));
			$finished_action_by = '';
			if (isset($item['taken_to_work'])) {
				$taken_to_work = GeneralHelper::GeDateFromTimestamp($item['taken_to_work']['timestamp']);
			}
			if (isset($item['finished'])) {
				$finished = GeneralHelper::GeDateFromTimestamp($item['finished']['timestamp']);
				if (!empty($item['finished']['action_by'])) {
					$finished_action_by = User::findOrFail($item['finished']['action_by'])->name;
				}
			}

			if (($status == 2 || $status == 4) && !isset($item['finished'])) { // old data
				$finished = $taken_to_work = $start;
				$timestamp_progress['taken'] = '';
				$timestamp_progress['finished'] = '';
			}

			$timestamp_progress = [
				'opened' => GeneralHelper::timeNiceDuration($finished - $start),
			];

			if (($status == 2 || $status == 4) && isset($item['finished'])) {
				$timestamp_progress['taken'] = GeneralHelper::timeNiceDuration($finished - $taken_to_work) . (!empty($finished_action_by) ? ' (' . $finished_action_by . ')' : '');
				$timestamp_progress['finished'] = date('Y-m-d H:i:s', $finished) . (!empty($finished_action_by) ? ' (' . $finished_action_by . ')' : '');
			}
			$item['timestamp_progress'] = $timestamp_progress;
		}
		// DB::listen(function ($query) {
		// 	var_dump($query->sql);
		// });

		return new Collection(['total' => $total, 'count' => $total, 'items' => $items]);
	}

	public function get(
		string $modelId,
		array $columns = ['*'],
		array $relations = [],
		array $appends = []
	): ?Model {
		$modal = $this->model->select($columns)->with($relations)->findOrFail($modelId)->append($appends);

		if (isset($modal->created_by_user)) {
			$modal->created_by_user_name = $modal->created_by_user->name . ' (' . $modal->created_by_user->account_email . ')';
		}

		StorageHelper::injectFiles('support', $modal, 'files');

		return $modal;
	}

	public function update(string $modelId, array $payload): bool
	{
		$model = TechSupport::findOrFail($modelId);

		$status = ((int)($payload['status'] ?? 0));

		if ((int)$model->status != $status && $status > 0) {

			if (((int)$model->status) == 2 || ((int)$model->status) == 4) {
				throw new Exception('You can\'t change status like this');
			}

			$data = [
				'timestamp' => GeneralHelper::ToMongoDateTime(time()),
				'action_by' => Auth::id(),
			];

			switch ($status) {
				case 1:   // => 'Open',
					{
						if (isset($payload['finished'])) {
							unset($payload['finished']);
						}
						if (isset($payload['taken_to_work'])) {
							unset($payload['taken_to_work']);
						}
						break;
					}
				case 2:   // => 'Rejected',
				case 4: { // => 'Completed'
						$payload['finished'] = $data;
						break;
					}
				case 3: { // => 'In Progress',
						if (isset($payload['finished'])) {
							unset($payload['finished']);
						}
						$payload['taken_to_work'] = $data;
						break;
					}
			}
		}

		StorageHelper::syncFiles('support', $this->model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'csv']);
		return $model->update($payload);
	}

	public function send_comment(string $ticket_id, array $payload): bool
	{
		$model = new TechSupportComment();

		$insert = [
			"comment" => $payload['comment'],
			"created_by" => Auth::id(),
			"ticket_id" => $ticket_id,
			"timestamp" => new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000)
		];

		$model->fill($insert);
		$model->save();
		$id = $model->id;

		return !empty($id);
	}

	private function new_ticket_number()
	{
		$charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$token = substr(str_shuffle($charsz), 0, 3);
		$TicketNumber = $token . '' . rand(10000, 99999);
		return $TicketNumber;
	}

	public function create(array $payload): ?Model
	{
		$payload['created_by'] = Auth::id();
		$payload['status'] = 1;
		$payload['ticket_number'] = $this->new_ticket_number();

		$var = date("Y-m-d H:i:s"); // . ' 00:00:00';
		$payload['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

		StorageHelper::syncFiles('support', $this->model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

		$model = $this->model->create($payload);

		$this->send_to_slack($payload);

		return $model->fresh();
	}

	private function get_endpoint_name(string $endpointId)
	{
		$where = ['_id' => new \MongoDB\BSON\ObjectId($endpointId)];
		$mongo = new MongoDBObjects('TrafficEndpoints', $where);
		$partner = $mongo->find();
		$result = ($partner['token'] ?? '');
		return $result;
	}

	private function get_broker_name(string $brokerId)
	{
		$where = ['_id' => new \MongoDB\BSON\ObjectId($brokerId)];
		$mongo = new MongoDBObjects('partner', $where);
		$partner = $mongo->find();
		$result = GeneralHelper::broker_name($partner);
		return $result;
	}

	private function send_to_slack($ticket)
	{
		$message = '';

		$message .=  'Ticket Number: ' . (isset($ticket['ticket_number']) ? $ticket['ticket_number'] : $ticket['_id']) . PHP_EOL;

		$message .=  (array_key_exists($ticket['type'], TechSupport::types) ? 'Type: ' . TechSupport::types[$ticket['type']] . PHP_EOL : '');
		$message .=  (!empty($ticket['note']) ? 'Note: ' . $ticket['note'] . PHP_EOL : '');

		if (!empty($ticket['broker'] ?? '')) {
			$message .=  'Broker: ' . $this->get_broker_name($ticket['broker']) . PHP_EOL;
		}
		if (!empty($ticket['traffic_endpoint'] ?? '')) {
			$message .=  'Endpoint: ' . $this->get_endpoint_name($ticket['traffic_endpoint']) . PHP_EOL;
		}

		$createdby = User::findOrFail($ticket['created_by']);
		if ($createdby) {
			$createdby = $createdby->name;
		} else {
			$createdby = '';
		}

		if (!empty($createdby)) {
			$message .= 'Created By: ' . $createdby . PHP_EOL;
		}

		// NotificationReporter::to('tech_support')->send($message, 'New Support Ticket');
		Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('tech_support', $message)));
		// NotificationReporter::to('tech_support')->slack($message);
	}
}
