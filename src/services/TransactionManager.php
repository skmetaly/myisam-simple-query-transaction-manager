<?php
namespace PaulB\services;

use PaulB\models\Lock;
use PaulB\models\QueryRunner;
use PaulB\models\Transaction;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class TransactionManager
 * Main class used to provide a Transaction instance
 * @package PaulB\services
 */
class TransactionManager extends Command
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
     * @var
     */
    protected $_output;

    /**
     * @var Lock
     */
    protected $_lock;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        global $capsule;

        $this->setOutput($output);

        $this->_lock = new Lock;

        //  Only for debugging
        //$capsule->getConnection('sys')->table('query_log')->delete();
    }

    /**
     * SETTER
     * @param OutputInterface $output
     */
    protected function setOutput(OutputInterface $output)
    {
        $this->_output = $output;
    }

    /**
     *
     */
    public function init()
    {
        $this->writeLn('Init');

        //  DEBUGGING
        //$this->_lock->clear();

        //$this->writeLn('Cleared all locks');
    }

    /**
     * @param $message
     */
    protected function writeLn($message)
    {
        $this->_output->writeln('<info>[Transaction manager] ' . $message . '</info>');
    }

    /**
     * Returns a unique transaction instance with a unique ID
     * @return Transaction
     */
    public function getTransaction()
    {
        return new Transaction(time() . rand(0, 100));
    }

    /**
     * Runs a transaction
     * Gets all commands that a transaction has and runs the individually
     * @param $transaction
     */
    public function runTransaction($transaction)
    {
        $commands = $transaction->getCommands();

        foreach ($commands as $command) {

            switch ($command[ 'type' ]) {

                case self::COMMAND_TYPE_QUERY: {
                    $this->runQuery($command, $transaction);
                }
            }
        }
    }

    /**
     * Runs an individual query
     * First it tries to aquire the locks
     * Then calls the query runner to run the query based on it's type
     *
     * @param $command
     * @param $transaction
     *
     * @return array|bool
     */
    public function runQuery($command, $transaction)
    {
        global $capsule;

        $successfulLock = $this->aquireLock($command, $transaction);

        if ($successfulLock == false) {
            $this->writeLn('<error> Could not aquire locks for transaction : ' . $transaction->getId() . ' and query : "' . $command[ 'query' ] . '" </error>');
            return false;
        }

        $queryRunner = new QueryRunner($command, $transaction);

        return $queryRunner->execute();
    }

    /**
     * Tries to aquires the lock
     * @param $command
     * @param $transaction
     *
     * @return bool|void
     */
    private function aquireLock($command, $transaction)
    {
        return $this->_lock->aquire($command, $transaction);
    }

}