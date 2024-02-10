<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\Driver\PDO\Statement as BaseStatement;
use Doctrine\DBAL\ParameterType;

final readonly class Statement implements StatementInterface
{
    public function __construct(
        private BaseStatement $statement,
        private ?ConnectionStats $connectionStats = null
    ) {
    }

    public function execute(): Result
    {
        $result = $this->statement->execute();

        if ($this->connectionStats instanceof ConnectionStats) {
            $this->connectionStats->counter++;
        }


        return $result;
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->statement->bindValue($param, $value, $type);
    }
}