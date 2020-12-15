<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Select\Appliers;

use Espo\Core\{
    Exceptions\Error,
    AclManager,
    Select\SelectManager,
    Select\AccessContror\FilterFactory as AccessControlFilterFactory,
    Select\AccessContror\FilterResolverFactory as AccessControlFilterResolverFactory,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

class AccessControlFilterApplier
{
    protected $acl;

    protected $entityType;
    protected $user;
    protected $accessControlFilterFactory;
    protected $accessControlFilterResolverFactory;
    protected $aclManager;
    protected $selectManager;

    public function __construct(
        string $entityType,
        User $user,
        AccessControlFilterFactory $accessControlFilterFactory,
        AccessControlFilterResolverFactory $accessControlFilterResolverFactory,
        AclManager $aclManager,
        SelectManager $selectManager
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->accessControlFilterFactory = $accessControlFilterFactory;
        $this->aclManager = $aclManager;
        $this->selectManager = $selectManager;

        $this->acl = $this->aclManager->createUserAcl($this->user);
    }

    public function apply(QueryBuilder $queryBuilder)
    {
        // For backward compatibility.
        if ($this->selectManager->hasInheritedAccessMethod()) {
            $this->selectManager->applyAccessToQueryBuilder($queryBuilder);

            return;
        }

        $accessControlFilterResolver = $this->accessControlFilterResolverFactory->create($this->entityType, $this->user);

        $filterName = $accessControlFilterResolver->resolve();

        if (!$filterName) {
            return;
        }

        // For backward compatibility.
        if ($selectManager->hasInheritedAccessFilterMethod($filterName)) {
            $selectManager->applyAccessFilterToQueryBuilder($queryBuilder, $filterName);

            return;
        }

        if ($this->accessControlFilterFactory->has($this->entityType, $filterName)) {
            $filter = $this->accessControlFilterFactory->create($this->entityType, $user, $filterName);

            $filter->apply($queryBuilder);

            return;
        }

        throw new Error("No access filter '{$filterName}' for '{$this->entityType}'.");
    }
}
