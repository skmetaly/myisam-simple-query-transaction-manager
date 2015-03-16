<?php

namespace PaulB\models;


/**
 * Class QueryRunner
 * @package PaulB\models
 */
class QueryRunner
{

    /**
     * @var command
     */
    protected $command;

    /**
     * @var transaction
     */
    protected $transaction;

    /**
     *  SELECT query identifier
     */
    const QUERY_SELECT = 'select';

    /**
     *  UPDATE query identifier
     */
    const QUERY_UPDATE = 'update';

    /**
     *  INSERT query identifier
     */
    const QUERY_INSERT = 'insert';

    /**
     *  DELETE query identifier
     */
    const QUERY_DELETE = 'delete';

    /**
     * Default constructor
     *
     * @param $command
     * @param $transaction
     */
    public function __construct($command, $transaction)
    {
        $this->command = $command;

        $this->transaction = $transaction;

    }

    /**
     * Executes the query
     *
     * Based on it's type it calls the appropriate procedure
     * @return array|bool
     */
    public function execute()
    {
        global $capsule;

        $queryType = $this->sortQuery($this->command[ 'query' ]);

        switch ($queryType) {
            case self::QUERY_SELECT:
                return $capsule->getConnection($this->command[ 'connection' ])->select($capsule->getConnection($this->command[ 'connection' ])->raw($this->command[ 'query' ]));
                break;

            case self::QUERY_UPDATE:
                return $this->runUpdate();
                break;

            case self::QUERY_INSERT:
                return $this->runInsert();
                break;

            case self::QUERY_DELETE:
                return $this->runDelete();
                break;
        }

    }

    /**
     * Runs an update query
     * @return bool
     */
    private function runUpdate()
    {
        global $capsule;

        $selectQuery = $this->updateToSelect();

        $records = $capsule->getConnection($this->command[ 'connection' ])->select($capsule->getConnection($this->command[ 'connection' ])->raw($selectQuery));

        //  We need to transform the update into a select in order to get the records affected
        $pattern = '/UPDATE( +)(?P<table>.*)( +)SET(?P<update_fields>.*)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $this->command[ 'query' ], $matches);

        $table = $matches[ 'table' ];

        //  Get what fields needs updating
        $updateFields = $matches[ 'update_fields' ];

        $updateFields = explode(',', $updateFields);

        foreach ($updateFields as $update) {

            $update_parts = explode('=', $update);

            $updates[ trim($update_parts[ 0 ]) ] = trim($update_parts[ 1 ]);
        }

        //  For each matched records
        foreach ($records as $record) {

            //  Log the update made for each field
            foreach ($updates as $field => $value) {

                //  We need to update the logger
                $query_log = [
                    'type' => 'update',
                    'table' => $table,
                    'database' => $this->command[ 'connection' ],
                    'field' => $field,
                    'new_value' => str_replace('"', '', $value),
                    'old_value' => $record[ $field ],
                    'transaction_id' => $this->transaction->getId(),
                    'row_id' => $record[ 'id' ]
                ];

                $capsule->getConnection('sys')->table('query_log')->insert($query_log);
            }
        }

        //  After we finish everything to log, we run the query
        $capsule->getConnection($this->command[ 'connection' ])->update($capsule->getConnection($this->command[ 'connection' ])->raw($this->command[ 'query' ]));

        return true;
    }

    /**
     * Runs an insert query
     *
     * @return bool
     */
    private function runInsert()
    {
        global $capsule;

        $pattern = '/INSERT( +)INTO( +)(?P<table>.*?)( *)\(/';

        preg_match($pattern, $this->command[ 'query' ], $matches);

        if (!isset($matches[ 'table' ])) {
            return false;
        }

        $table = $matches[ 'table' ];

        $capsule->getConnection($this->command[ 'connection' ])->insert($capsule->getConnection($this->command[ 'connection' ])->raw($this->command[ 'query' ]));

        $id = $capsule->getConnection($this->command[ 'connection' ])->table($table)->select('id')->orderBy('id',
            'DESC')->first();

        $id = $id[ 'id' ];

        //  we need to update the logger
        $query_log = [
            'type' => 'insert',
            'table' => $table,
            'database' => $this->command[ 'connection' ],
            'transaction_id' => $this->transaction->getId(),
            'row_id' => $id
        ];

        $capsule->getConnection('sys')->table('query_log')->insert($query_log);
    }

    /**
     * Runs a delete query
     * @return bool
     */
    private function runDelete()
    {
        global $capsule;

        $selectQuery = $this->deleteToSelect();

        $records = $capsule->getConnection($this->command[ 'connection' ])->select($capsule->getConnection($this->command[ 'connection' ])->raw($selectQuery));

        //  Delete pattern
        $pattern = '/DELETE( +)FROM( +)(?P<table>.*)( +)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $this->command[ 'query' ], $matches);

        $table = $matches[ 'table' ];

        //  For each matched records
        foreach ($records as $record) {

            //  We need to update the logger
            $query_log = [
                'type' => 'delete',
                'table' => $table,
                'database' => $this->command[ 'connection' ],
                'transaction_id' => $this->transaction->getId(),
                'row_id' => $record[ 'id' ],
                'deleted_fields'=>json_encode($record)
            ];

            $capsule->getConnection('sys')->table('query_log')->insert($query_log);
        }

        //  After we finish everything to log, we run the query
        $capsule->getConnection($this->command[ 'connection' ])->delete($capsule->getConnection($this->command[ 'connection' ])->raw($this->command[ 'query' ]));

        return true;
    }

    /**
     * Transforms a delete query to select
     *
     * It is used internaly for aquiring the locks
     * @return bool|string
     */
    protected function deleteToSelect()
    {
        //  we need to transform the DELETE into a select in order to get the records affected
        $pattern = '/DELETE( +)FROM( +)(?P<table>.*)( +)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $this->command[ 'query' ], $matches);

        if (!isset($matches[ 'table' ])) {
            return false;
        }

        $table = $matches[ 'table' ];

        if (!isset($matches[ 'where' ])) {
            $where = '';
        } else {
            $where = ' WHERE ' . $matches[ 'where' ];
        }

        //  create the select statement
        $selectQuery = 'SELECT * FROM ' . $table . ' ' . $where;

        return $selectQuery;
    }

    /**
     * Transforms a update query to select
     *
     * It is used internaly for aquiring the locks
     * @return bool|string
     */
    private function updateToSelect()
    {
        //  we need to transform the update into a select in order to get the records affected
        $pattern = '/UPDATE( +)(?P<table>.*)( +)SET(.*)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $this->command[ 'query' ], $matches);

        if (!isset($matches[ 'table' ])) {
            return false;
        }

        $table = $matches[ 'table' ];

        if (!isset($matches[ 'where' ])) {
            $where = '';
        } else {
            $where = ' WHERE ' . $matches[ 'where' ];
        }

        //  create the select statement
        $selectQuery = 'SELECT * FROM ' . $table . ' ' . $where;

        return $selectQuery;
    }

    /**
     * Returns the type of the query
     *
     * @param $query
     *
     * @return string
     */
    private function sortQuery($query)
    {
        if (strpos(strtolower($query), 'select') !== false) {

            return self::QUERY_SELECT;
        }

        if (strpos(strtolower($query), 'insert') !== false) {

            return self::QUERY_INSERT;
        }

        if (strpos(strtolower($query), 'update') !== false) {

            return self::QUERY_UPDATE;
        }

        if (strpos(strtolower($query), 'delete') !== false) {

            return self::QUERY_DELETE;
        }
    }

    /**
     * GETTER
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * GETTER
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * SETTER
     * @param mixed $command
     */
    public function setCommand($command)
    {
        $this->command = $command;
    }


    /**
     * @param mixed $transaction
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
    }
}