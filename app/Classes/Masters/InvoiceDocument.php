<?php

namespace App\Classes\Masters;

use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\GeneralHelper;
use Konekt\PdfInvoice\InvoicePrinter;
use MongoDB\BSON\ObjectId;

class InvoiceDocument
{
    public $id;
    private $update = [];

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function set_date($timestamp)
    {
        $this->update['invoice_dt'] = $timestamp;
    }

    public function set_from($company_details)
    {
        $this->update['details']['from'] = $company_details;
    }

    public function set_to($company_details)
    {
        $this->update['details']['to'] = $company_details;
    }

    public function set_payment_method($payment_method)
    {
        $payment_method_body = [
            strtoupper($payment_method['payment_method'] . ": " . $payment_method['currency_code'] . $payment_method['currency_crypto_code'])
        ];
        $payment_method_fields = [
            'bank_name' => 'Bank Name',
            'swift' => 'Swift',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'wallet' => 'Wallet',
            'notes' => 'Notes'
        ];
        if (isset($payment_method['wallet2']) && !empty($payment_method['wallet2'])) {
            $payment_method_fields['wallet'] = 'Wallet ERC 20';
            $payment_method_fields['wallet2'] = 'Wallet TRC 20';
        }
        foreach ($payment_method_fields as $field => $title) {
            if (!empty($payment_method[$field])) {
                $payment_method_body[] = $title . ": " . $payment_method[$field];
            }
        }
        $this->update['details']['payment'] = $payment_method_body;
    }

    public function add_payment_request_items($payment_request)
    {
        if ($payment_request['type'] == 'prepayment')
        {
            $this->update['items'] = [[
                'name' => "Prepayment",
                'cost' => $payment_request['total'],
                'count' => 1,
            ]];
        }
        else
        {
            $ts = (array)$payment_request['from'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $from = date("Y-m-d", $seconds);
    
            $ts = (array)$payment_request['to'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $to = date("Y-m-d", $seconds);
    
            $this->update['items'] = [[
                'name' => "Period " . $from . ' - ' . $to,
                'cost' => $payment_request['total'],
                'count' => 1,
            ]];
        }
        $this->update['details']['payment_fee'] = $payment_request['payment_fee'];
    }

    public function update()
    {
        if (!empty($this->update)) {
            $this->update['dt_modify'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);

            $where = ['_id' => new \MongoDB\BSON\ObjectId($this->id)];
            $mongo = new MongoDBObjects('invoices', $where);
            $mongo->update($this->update);
            
            $this->update = [];
        }
    }

    public function render_to_output()
    {
        $where = ['_id' => new \MongoDB\BSON\ObjectId($this->id)];
        $mongo = new MongoDBObjects('invoices', $where);
        $invoice = $mongo->find();
        
        $doc = new InvoicePrinter('A4', '$', 'en');
        //$invoice->setLogo("theme/img/core/logo.png");
        $doc->setColor("#007fff");
        $doc->setType("Invoice");
        $doc->setReference($invoice['invoice_number']);
        $doc->setDate(date('M dS ,Y', $invoice['invoice_dt']));

        $details = (array)$invoice['details']['from'];
        $doc->setFrom(array_merge([$details['organization_name']], (array)$details['organization_address']));

        $details = (array)($invoice['details']['to'] ?? []);
        $doc->setTo(array_merge([$details['organization_name']], (array)$details['organization_address']));

        $items_total = 0;
        foreach ($invoice['items'] as $item) {
            $doc->addItem($item['name'], '', $item['count'], false, $item['cost'], false, false);
            $items_total += $item['count'] * $item['cost'];
        }

        $total_fee = self::get_fee_value($items_total, $invoice['details']['payment_fee'] ?? 0);
        if ($total_fee > 0) {
            $doc->addTotal('Fee ' . $invoice['details']['payment_fee'] . '%', $total_fee);
        }
        $doc->addTotal('Total', $items_total + $total_fee);

        if (isset($invoice['details']['payment'])) {
            $doc->addTitle("Payment Method");

            foreach ($invoice['details']['payment'] as $paragraph) {
                $doc->addParagraph($paragraph);
            }
        }

        return $doc->render("Invoice " . $invoice['invoice_number'] . ".pdf", 'I');
    }

    public static function get_next_invoice_number($numerator)
    {
        while (true) {
            $where = ['invoice_numerator' => $numerator];
            $mongo = new MongoDBObjects('settings', $where);
            
            $current = $mongo->find();
            if (!$current) {
                throw new \Exception('Invoice numerator not exists: '.$numerator);
            }
            
            $mongo = new MongoDBObjects('settings', $current);
            $update = [ 'next_id' => 1 ];
            if ($mongo->findOneAndUpdate($update)) {
                return $current['next_id'];
            }
        }
    }

    public static function get_billing_entity_details($billing_entity)
    {
        $all_countries = GeneralHelper::countries();
        $country = $all_countries[$billing_entity['country_code']] ?? $billing_entity['country_code'];

        return [
            'organization_name' => $billing_entity['company_legal_name'],
            'organization_address' => [
                $billing_entity['city'],
                $billing_entity['zip_code'] . " " . $billing_entity['region'],
                $country
            ]
        ];
    }

    public static function insert($collection, $id)
    {
        $insert = [
            'invoice_number' => self::get_next_invoice_number('invoices'),
            'dt_created' => new \MongoDB\BSON\UTCDateTime(time() * 1000),
            'created_by' => GeneralHelper::get_current_user_token(),
            'foreign_collection_name' => $collection,
            'foreign_collection_key' => new ObjectId($id),
        ];
        $mongo = new MongoDBObjects('invoices', $insert);
        return $mongo->insertWithToken();
    }

    public static function get_fee_value($total, $fee)
    {
        // return ceil($total * 100 / (1 - $fee / 100)) / 100 - $total;
        return ceil($total * 100 / (100 - $fee)) - $total;
    }
}