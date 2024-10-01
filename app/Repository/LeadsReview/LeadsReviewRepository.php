<?php

namespace App\Repository\LeadsReview;

use App\Models\User;
use App\Models\Leads;
use App\Helpers\StorageHelper;
use App\Models\LeadsReviewSupport;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\SlackNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Repository\LeadsReview\ILeadsReviewRepository;

class LeadsReviewRepository extends BaseRepository implements ILeadsReviewRepository
{

	/**
	 * @var Model
	 */
	protected $model;

	public function __construct(Leads $model)
	{
		$this->model = $model;
	}

	public function index(array $columns = ['*'], array $relations = [], array $payload): Collection
	{
		$timeframe = $payload['timeframe'] ?? '';

		$time = explode(' - ', $timeframe);
		$start = strtotime($time[0] . " 00:00:00");
		$end = strtotime($time[1] . " 23:59:59");

		$start = new \MongoDB\BSON\UTCDateTime($start * 1000);
		$end   = new \MongoDB\BSON\UTCDateTime($end * 1000);

		$query = $this->model->with($relations)
			->where('Timestamp', '>=', $start)
			->where('Timestamp', '<=', $end)
			->where('test_lead', '=', 0)
			->where('match_with_broker', '=', 1)
			->where('depositor', '=', false);

		if (!empty($payload['traffic_endpoint'])) {
			$query = $query->where('TrafficEndpoint', '=', $payload['traffic_endpoint']);
		}

		if (!empty($payload['broker'])) {
			$query = $query->where('brokerId', '=', $payload['broker']);
		}

		$review_status = $payload['review_status'] ?? [];
		$query = $query->where(function ($q) use ($review_status) {
			foreach ($review_status as $v) {
				if ($v == 'unchecked') {
					$q->whereRaw(['review_status' => ['$exists' => false]]);
				} else {
					$q->orWhere('review_status', '=', (int)$v);
				}
			}
		});

		$items = $query->get($columns);

		return $items;
	}

	public function checked(string $leadId): bool
	{
		$model = Leads::findOrFail($leadId);
		return $model->update(['review_status' => 1]);
	}

	private function new_ticket_number()
	{
		$charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$token = substr(str_shuffle($charsz), 0, 3);
		$TicketNumber = $token . '' . rand(10000, 99999);
		return $TicketNumber;
	}


	public function index_ticket(array $columns = ['*'], array $relations = []): Collection
	{
		$items = LeadsReviewSupport::with($relations)->get($columns)
			->sortByDesc(function ($item, $key) {
				$a = (array)($item->timestamp ?? []);
				return intval($a['milliseconds'] ?? 0);
			})->values();
		return $items;
	}

	public function page_ticket(int $page, array $payload, array $columns = ['*'], array $relations = []): Collection
	{
		$query = LeadsReviewSupport::with($relations);

		if (!empty($payload['status'])) {
			if (is_array($payload['status'])) {
				foreach ($payload['status'] as &$status) {
					$status = (int)$status;
				}
				$query = $query->whereIn('status', (array)$payload['status']);
			} else if ((int)$payload['status'] > 0) {
				$query = $query->where('status', '=', (int)$payload['status']);
			}
		}
		if (!empty($payload['search'])) {
			$search = '%' . $payload['search'] . '%';
			$query = $query
				->where('ticketNumber', 'like', $search);
			// ->where('users.account_email', 'like', $search);
			// ->orWhere('users.name', 'like', $search);
		}

		$query = $query->get($columns);

		$total = $query->count();

		$query = $query->sortByDesc(function ($item, $key) {
			$a = (array)($item->timestamp ?? []);
			return intval($a['milliseconds'] ?? 0);
		});

		$items = $query->skip(($page - 1) * 10)
			->take(10)
			->values();

		return new Collection(['total' => $total, 'items' => $items]);
	}

	public function get_ticket(
		string $modelId,
		array $columns = ['*'],
		array $relations = [],
		array $appends = []
	): ?Model {

		$modal = LeadsReviewSupport::query()->with($relations)->where('_id', '=', $modelId)->first($columns);

		if (isset($modal->created_by_user)) {
			$modal->created_by_user_name = $modal->created_by_user->name . ' (' . $modal->created_by_user->account_email . ')';
		}

		StorageHelper::injectFiles('leads_review_support', $modal, 'files');

		return $modal;
	}

	public function create_ticket(string $leadId, array $payload): bool
	{
		$model = new LeadsReviewSupport();
		$payload['createdBy'] = Auth::id();
		$payload['status'] = 1;
		$payload['leadId'] = $leadId;
		$payload['ticketNumber'] = $this->new_ticket_number();

		$var = date("Y-m-d H:i:s"); // . ' 00:00:00';
		$payload['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

		StorageHelper::syncFiles('leads_review_support', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

		$model->fill($payload);

		$this->send_to_slack($payload);

		return $model->save($payload);
	}

	private function send_to_slack($ticket)
	{
		$message = '';

		$message .=  'Ticket Number: ' . (isset($ticket['ticketNumber']) ? $ticket['ticketNumber'] : $ticket['_id']) . PHP_EOL;

		$message .=  (!empty($ticket['note']) ? 'Note: ' . $ticket['note'] . PHP_EOL : '');

		$createdby = User::findOrFail($ticket['createdBy']);
		if ($createdby) {
			$createdby = $createdby->name;
		} else {
			$createdby = '';
		}

		if (!empty($createdby)) {
			$message .= 'Created By: ' . $createdby . PHP_EOL;
		}

		Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('leads-review', $message)));
		// NotificationReporter::to('tech_support')->slack($message);
	}

	public function update_ticket(string $modelId, array $payload): bool
	{
		$model = LeadsReviewSupport::findOrFail($modelId);
		// StorageHelper::syncFiles('leads_review_support', $model, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
		return $model->update($payload);
	}
}
