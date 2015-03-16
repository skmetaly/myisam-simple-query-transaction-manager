<?php

namespace PaulB\models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Lock
 * @package PaulB\models
 */
class Lock extends Model
{

    /**
     * @var bool
     */
    public $timestamps = false;

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
     * Used for debugging purposes
     *
     * @param null $transaction
     */
    public function clear($transaction = null)
    {
        global $capsule;

        if ($transaction) {
            $capsule->getConnection('sys')->table('locks')->where('transaction_id','=',$transaction->getId())->delete();

        }else{

            $capsule->getConnection('sys')->table('locks')->delete();
        }
    }

    /**
     * Aquire a lock for a query
     * @param $command
     * @param $transaction
     *
     * @return bool|void
     */
    public function aquire($command, $transaction)
    {
        $queryType = $this->sortQuery($command[ 'query' ]);

        switch ($queryType) {
            case self::QUERY_SELECT:
                return $this->aquireLockSelect($command, $transaction);
                break;

            case self::QUERY_UPDATE:
                return $this->aquireLockUpdate($command, $transaction);
                break;

            case self::QUERY_INSERT:
                return $this->aquireLockInsert($command, $transaction);
                break;

            case self::QUERY_DELETE:
                return $this->aquireLockDelete($command, $transaction);
                break;
        }
    }

    /**
     * Sorts a query based on it's type
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
     * Aquire lock for a select query
     *
     * @param $command
     * @param $transaction
     *
     * @return bool
     */
    private function aquireLockSelect($command, $transaction)
    {
        global $capsule;

        $query = $command[ 'query' ];

        $records = $capsule->getConnection($command[ 'connection' ])->select($capsule->getConnection($command[ 'connection' ])->raw($query));

        $recordsMeta = $capsule->getConnection($command[ 'connection' ])->select($capsule->getConnection($command[ 'connection' ])->raw(' explain ' . $query));

        $alreadyLocked = 0;

        //  checking to see if the respective read lock is locked
        foreach ($records as $record) {

            $alreadyLocked += $capsule->getConnection('sys')->table('locks')->select('id')->where('row_id',
                $record[ 'id' ])->where('type', 'read')->where('table', $recordsMeta[ 0 ][ 'table' ])->where('database',
                $command[ 'connection' ])->where('transaction_id', '!=', $transaction->getId())->count();
        }

        if ($alreadyLocked > 0) {

            return false;
        }

        //  read lock does not exist, check if write lock exist, if it doesn't exist, we add it
        foreach ($records as $record) {

            $alreadyLocked = $capsule->getConnection('sys')->table('locks')->select('id')->where('row_id',
                $record[ 'id' ])->where('type', 'write')->where('table',
                $recordsMeta[ 0 ][ 'table' ])->where('database', $command[ 'connection' ])->count();

            if ($alreadyLocked == 0) {

                $capsule->getConnection('sys')->table('locks')->insert([
                    'row_id' => $record[ 'id' ],
                    'type' => 'write',
                    'table' => $recordsMeta[ 0 ][ 'table' ],
                    'database' => $command[ 'connection' ],
                    'transaction_id' => $transaction->getId()
                ]);
            }
        }

        return true;
    }

    /**
     * Aquire lock for a update query
     *
     * @param $command
     * @param $transaction
     *
     * @return bool
     */
    private function aquireLockUpdate($command, $transaction)
    {
        global $capsule;

        //  we need to transform the update into a select in order to get the records affected
        $pattern = '/UPDATE( +)(?P<table>.*)( +)SET(.*)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $command[ 'query' ], $matches);

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
        $selectQuery = 'SELECT id FROM ' . $table . ' ' . $where;

        $records = $capsule->getConnection($command[ 'connection' ])->select($capsule->getConnection($command[ 'connection' ])->raw($selectQuery));

        $alreadyLocked = 0;

        //  checking to see if the respective read lock is locked
        foreach ($records as $record) {

            $alreadyLocked += $capsule->getConnection('sys')->table('locks')->select('id')->where('row_id',
                $record[ 'id' ])->where('type', 'write')->where('table', $table)->where('database',
                $command[ 'connection' ])->where('transaction_id', '!=', $transaction->getId())->count();
        }

        if ($alreadyLocked > 0) {
            return false;
        }

        //  add read and write locks to the affected tables
        foreach ($records as $record) {

            //  aquire for read
            $this->insertLock($record, $command, $table, $transaction, 'read');

            //  aquire for write
            $this->insertLock($record, $command, $table, $transaction, 'write');
        }

        return true;
    }

    /**
     * Aquire lock for a insert query
     *
     * @param $command
     * @param $transaction
     *
     * @return bool
     */
    private function aquireLockInsert($command, $transaction)
    {
        return true;
    }

    /**
     * Aquire lock for an insert query
     *
     * @param $record
     * @param $command
     * @param $table
     * @param $transaction
     * @param $type
     */
    private function insertLock($record, $command, $table, $transaction, $type)
    {
        global $capsule;

        $alreadyLocked = $capsule->getConnection('sys')->table('locks')->select('id')->where('row_id',
            $record[ 'id' ])->where('type', $type)->where('table', $table)->where('database',
            $command[ 'connection' ])->count();

        if ($alreadyLocked == 0) {

            $capsule->getConnection('sys')->table('locks')->insert([
                'row_id' => $record[ 'id' ],
                'type' => $type,
                'table' => $table,
                'database' => $command[ 'connection' ],
                'transaction_id' => $transaction->getId()
            ]);
        }
    }

    /**
     * Aquire lock for a delete query
     *
     * @param $command
     * @param $transaction
     *
     * @return bool
     */
    private function aquireLockDelete($command, $transaction)
    {
        global $capsule;

        //  we need to transform the DELETE into a select in order to get the records affected
        $pattern = '/DELETE( +)FROM( +)(?P<table>.*)( +)WHERE( +)(?P<where>.*)/';

        preg_match($pattern, $command[ 'query' ], $matches);

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
        $selectQuery = 'SELECT id FROM ' . $table . ' ' . $where;

        $records = $capsule->getConnection($command[ 'connection' ])->select($capsule->getConnection($command[ 'connection' ])->raw($selectQuery));

        $alreadyLocked = 0;

        //  checking to see if the respective read lock is locked
        foreach ($records as $record) {

            $alreadyLocked += $capsule->getConnection('sys')->table('locks')->select('id')->where('row_id',
                $record[ 'id' ])->where('type', 'write')->where('table', $table)->where('database',
                $command[ 'connection' ])->where('transaction_id', '!=', $transaction->getId())->count();
        }

        if ($alreadyLocked > 0) {

            return false;
        }

        //  add read and write locks to the affected tables
        foreach ($records as $record) {

            //  aquire for read
            $this->insertLock($record, $command, $table, $transaction, 'read');

            //  aquire for write
            $this->insertLock($record, $command, $table, $transaction, 'write');
        }

        return true;
    }
}