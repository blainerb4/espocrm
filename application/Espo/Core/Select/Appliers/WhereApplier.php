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
    //Select\SelectManager,
    Select\Where\Params,
    Select\Where\Converter,
    Select\Where\ConverterFactory,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\QueryParams\Parts\WhereClause,
    Entities\User,
};

class WhereApplier
{
    protected $entityType;
    protected $user;
    protected $converterFactory;
    protected $permissionsCheckerFactory;

    public function __construct(
        string $entityType,
        User $user,
        ConverterFactory $converterFactory,
        PermissionsCheckerFactory $permissionsCheckerFactory
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->converterFactory = $converterFactory;
        $this->permissionsCheckerFactory = $permissionsCheckerFactory;
    }

    public function apply(QueryBuilder $queryBuilder, array $where, Params $params)
    {
        if (
            $params->applyWherePermissionsCheck() ||
            !$params->allowComplexExpressions()
        ) {
            $permissionsChecker = $this->permissionsCheckerFactory->create($entityType, $user);

            $permissionsChecker->check($where, $params);
        }

        $converter = $this->converterFactory->create($this->entityType, $this->user);

        $whereClause = $converter->convert($queryBuilder, $where);

        $queryBuilder->where(
            $whereClause->getRaw()
        );
    }
}
