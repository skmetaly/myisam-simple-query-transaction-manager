<?php


namespace PaulB\models;

/**
 * Class Transaction
 * @package PaulB\models
 */
/**
 * Class Transaction
 * @package PaulB\models
 */
class Transaction
{

    /**
     *  COMMIT COMMAND TYPE CONSTANT
     */
    const COMMAND_TYPE_COMMIT = 'commit';

    /**
     * COMMIT COMMAND TYPE QUERY
     */
    const COMMAND_TYPE_QUERY = 'query';

    /**
     * @var Output
     */
    protected $output;

    /**
     * @var
     */
    protected $id;

    /**
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;

        $this->commands = [];
    }

    /**
     * SETTER
     * @param $output
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * GETTER
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *  Just to simulate the beginning of transaction
     *  In our case, 2 phase locking doesn't do anything until a query is ran
     */
    public function beginTransaction()
    {

    }
    /**
     * Adds a query to the command lists
     * @param $query
     * @param $connection
     */
    public function addQuery($query, $connection)
    {
        $this->commands[ ] = [
            'type' => 'query',
            'query' => $query,
            'connection' => $connection
        ];
    }

    /**
     * @param $query
     * @param $connection
     *
     * @return mixed
     * @throws \Exception
     */
    public function runQuery($query, $connection)
    {
        try {
            global $transactionManager;

            $command = [
                'type' => 'query',
                'query' => $query,
                'connection' => $connection
            ];

            $this->commands[ ] = $command;

            do {
                $response = $transactionManager->runQuery($command, $this);

                if ($response === false) {
                    sleep(2);

                } else {

                    return $response;
                }

            } while ($response == false);

        } catch (\Exception $e) {
            $this->output->writeln('<error> Exception caught for query : ' . $query . '</error>.');

            $this->output->writeln('<error> Rollbacking</error>.');

            $this->rollback();

            throw $e;
        }
    }

    /**
     *
     */
    public function rollback()
    {
        //  get all from query log
        $rollback = new Rollback($this);

        $rollback->execute();
    }

    /**
     *
     */
    public function commit()
    {
        $this->commands[ ] = [
            'type' => 'commit'
        ];

        $this->clearQueryLogs();

        $this->clearLocks();
    }

    /**
     * @return int
     */
    private function clearQueryLogs()
    {
        global $capsule;

        return $capsule->getConnection('sys')->table('query_log')->where('transaction_id', '=',
            $this->getId())->delete();
    }

    /**
     *
     */
    private function clearLocks()
    {
        $lock = new Lock();

        $lock->clear($this);
    }

    /**
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }
}