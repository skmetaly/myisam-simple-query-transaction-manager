<?php

namespace PaulB\models;


/**
 * Class Rollback
 *
 * Rollback mechanism for queries
 * @package PaulB\models
 */
class Rollback
{
    /**
     * @var the transaction
     */
    protected $transaction;

    /**
     * @var failed queries
     */
    protected $failedQuery;

    /**
     *  QUERY
     */
    const QUERY_SELECT = 'select';

    /**
     *  QUERY
     */
    const QUERY_UPDATE = 'update';

    /**
     *  QUERY
     */
    const QUERY_INSERT = 'insert';

    /**
     *  QUERY
     */
    const QUERY_DELETE = 'delete';

    /**
     * Default constructor
     * @param $transaction
     */
    public function __construct($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     *  Executes a rollback
     *      -   executes the queries in reverse order
     *      -   clears the query logs
     *      -   clears the locks
     */
    public function execute()
    {
        $this->rollback();

        $this->clearQueryLogs();

        $this->clearLocks();
    }

    /**
     *  Rollbacks a transaction
     *  Based on it's type it will execute the inverse query
     * @return bool
     */
    public function rollback()
    {
        global $capsule;

        $records = $capsule->getConnection('sys')->table('query_log')->where('transaction_id',
            $this->transaction->getId())->orderBy('id', 'DESC')->get();

        foreach ($records as $record) {

            switch ($record[ 'type' ]) {

                case self::QUERY_INSERT:
                    $response = $this->rollbackInsert($record);
                    break;

                case self::QUERY_UPDATE:
                    $response = $this->rollbackUpdate($record);
                    break;

                case self::QUERY_DELETE:
                    $response = $this->rollbackDelete($record);
                    break;
            }

            if($response == 0){

                $this->failedQuery = $record;
                return false;
            }
        }
    }

    /**
     * Rollback an insert query
     *
     * @param $record
     *
     * @return int
     */
    private function rollbackInsert($record)
    {
        global $capsule;

        return $capsule->getConnection($record[ 'database' ])->table($record[ 'table' ])->where('id', '=',
            $record[ 'row_id' ])->delete();
    }

    /**
     * Rollback an update query
     *
     * @param $record
     *
     * @return int
     */
    private function rollbackUpdate($record)
    {
        global $capsule;

        return $capsule->getConnection($record[ 'database' ])->table($record[ 'table' ])->where('id', '=',
            $record[ 'row_id' ])->update([
            $record['field']=>$record['old_value']
        ]);
    }

    /**
     * Rollbacks a delete query
     * @param $record
     *
     * @return bool
     */
    private function rollbackDelete($record)
    {
        global $capsule;

        $deletedFields = json_decode($record['deleted_fields'],true);

        return $capsule->getConnection($record['database'])->table($record['table'])->insert($deletedFields);
    }

    /**
     * GETTER
     * Returns the failed queries
     *
     * @return mixed
     */
    public function getFailedQuery()
    {
        return $this->failedQuery;
    }

    /**
     * SETTER
     * Set's the failed records
     *
     * @param mixed $failedQuery
     */
    public function setFailedQuery($failedQuery)
    {
        $this->failedQuery = $failedQuery;
    }

    /**
     * Clears all query logs for the transaction
     * @return int
     */
    private function clearQueryLogs()
    {
        global $capsule;

        return $capsule->getConnection('sys')->table('query_log')->where('transaction_id','=',$this->transaction->getId())->delete();
    }

    /**
     *  Clear all locks for the transaction
     */
    private function clearLocks()
    {
        $lock = new Lock();

        $lock->clear($this->transaction);
    }
}