<?php

namespace Plugin\MPBC43\Repository;

use Plugin\MPBC43\Entity\Config;
use Eccube\Repository\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConfigRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function get(): ?Config
    {
        $config = $this->findOneBy([]);
        if (!$config) {
            $config = new Config();
            $config->setProductNameDisplayType('customer_input');
        }
        return $config;
    }
}
