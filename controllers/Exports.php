<?php

namespace Martin\Forms\Controllers;

use SplTempFileObject;
use League\Csv\AbstractCsv;
use Backend\Classes\Controller;
use Martin\Forms\Models\Record;
use Backend\Facades\BackendMenu;
use League\Csv\Writer as CsvWriter;

class Exports extends Controller
{
    public $requiredPermissions = ['martin.forms.access_exports'];

    public $implement = [
        'Backend.Behaviors.FormController',
    ];

    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Martin.Forms', 'forms', 'exports');
    }

    public function index()
    {
        $this->pageTitle = e(trans('martin.forms::lang.controllers.exports.title'));
        $this->create('frontend');
    }

    public function csv()
    {

        $records = Record::orderBy('created_at');

        // FILTER GROUPS
        if (!empty($groups = post('Record.filter_groups'))) {
            $records->whereIn('group', $groups);
        }

        // FILTER DATE
        if (!empty($date_after = post('Record.filter_date_after'))) {
            $records->whereDate('created_at', '>=', $date_after);
        }

        // FILTER DATE
        if (!empty($date_before = post('Record.filter_date_before'))) {
            $records->whereDate('created_at', '<=', $date_before);
        }

        // FILTER DELETED
        if (post('Record.options_deleted')) {
            $records->withTrashed();
        }

        // CREATE CSV
        $csv = CsvWriter::createFromFileObject(new SplTempFileObject());

        // CHANGE DELIMTER
        if (post('Record.options_delimiter')) {
            $csv->setDelimiter(';');
        }

        // SET UTF-8 Output
        if (post('Record.options_utf')) {
            $csv->setOutputBOM(AbstractCsv::BOM_UTF8);
        }

        // CSV HEADERS
        $headers = [];

        // METADATA HEADERS
        if (post('Record.options_metadata')) {
            $meta_headers = [
                e(trans('martin.forms::lang.controllers.records.columns.id')),
                e(trans('martin.forms::lang.controllers.records.columns.group')),
                e(trans('martin.forms::lang.controllers.records.columns.ip')),
                e(trans('martin.forms::lang.controllers.records.columns.created_at')),
            ];
            $headers = array_merge($meta_headers, $headers);
        }

        // ADD STORED FIELDS AS HEADER ROW IN CSV
        $filteredRecords = $records->get();
        $record = $filteredRecords->first();
        $headers = array_merge($headers, array_keys($record->form_data_arr));

        // ADD FILES HEADER
        if (post('Record.options_files')) {
            $headers[] = e(trans('martin.forms::lang.controllers.records.columns.files'));
        }

        // ADD HEADERS
        $csv->insertOne($headers);

        // WRITE CSV LINES
        foreach ($records->get() as $row) {
            $data = (array) json_decode($row['form_data']);

            // IF DATA IS ARRAY CONVERT TO JSON STRING
            foreach ($data as $field => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$field] = json_encode($value);
                }
            }

            // ADD METADATA IF NEEDED
            if (post('Record.options_metadata')) {
                array_unshift($data, $row['id'], $row['group'], $row['ip'], $row['created_at']);
            }

            // ADD ATTACHED FILES
            if (post('Record.options_files') && $row->files->count() > 0) {
                $data[] = $row->filesList();
            }

            $csv->insertOne($data);
        }

        // RETURN CSV
        $csv->output('records.csv');
        exit();
    }
}
