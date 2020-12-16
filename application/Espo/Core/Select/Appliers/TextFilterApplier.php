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
    Utils\Metadata,
    Utils\Config,
    Select\Text\FilterParams,
    Select\Text\FullTextSearchData,
    Select\Text\FullTextSearchDataComposerFactory,
    Select\Text\FullTextSearchDataComposerParams,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\EntityManager,
    Entities\User,
};

class TextFilterApplier
{
    protected $useContainsAttributeList = [];

    protected $fullTextRelevanceThreshold = null;

    protected $fullTextOrderType = self::FT_ORDER_COMBINTED;

    protected $fullTextOrderRelevanceDivider = 5;

    const FT_ORDER_COMBINTED = 0;

    const FT_ORDER_RELEVANCE = 1;

    const FT_ORDER_ORIGINAL = 3;

    const MIN_LENGTH_FOR_CONTENT_SEARCH = 4;

    private $seed = null;

    protected $entityType;

    protected $user;
    protected $config;
    protected $metadata;
    protected $entityManager;
    protected $fullTextSearchDataComposerFactory;

    public function __construct(
        string $entityType,
        User $user,
        Config $config,
        Metadata $metadata,
        EntityManager $entityManager,
        FullTextSearchDataComposerFactory $fullTextSearchDataComposerFactory
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->config = $config;
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
        $this->fullTextSearchDataComposerFactory = $fullTextSearchDataComposerFactory;
    }

    public function apply(QueryBuilder $queryBuilder, string $filter, FilterParams $params)
    {
        $noFullText = $params->noFullTextSearch();

        $fieldList =
            $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'textFilterFields']
            ) ??
            ['name'];

        $orGroup = [];

        $textFilterContainsMinLength =
            $this->config->get('textFilterContainsMinLength') ??
            self::MIN_LENGTH_FOR_CONTENT_SEARCH;

        $fullTextSearchData = null;

        $forceFullTextSearch = false;

        $useFullTextSearch = $params->forceFullTextSearch();

        if (mb_strpos($textFilter, 'ft:') === 0) {
            $textFilter = mb_substr($textFilter, 3);

            $useFullTextSearch = true;
            $forceFullTextSearch = true;
        }

        $filterForFullTextSearch = $textFilter;

        $skipWidlcards = false;

        if (mb_strpos($textFilter, '*') !== false) {
            $skipWidlcards = true;

            $textFilter = str_replace('*', '%', $textFilter);
        }

        $filterForFullTextSearch = str_replace('%', '*', $filterForFullTextSearch);

        $skipFullTextSearch = false;

        if (!$forceFullTextSearch) {
            if (mb_strpos($filterForFullTextSearch, '*') === 0) {
                $skipFullTextSearch = true;
            }
            else if (mb_strpos($filterForFullTextSearch, ' *') !== false) {
                $skipFullTextSearch = true;
            }
        }

        if ($noFullText) {
            $skipFullTextSearch = true;
        }

        $fullTextSearchData = null;

        if (!$skipFullTextSearch) {
            $fullTextSearchIsAuxiliary = !$useFullTextSearch;

            $fullTextSearchData = $this->getFullTextSearchData(
                $filterForFullTextSearch, $fullTextSearchIsAuxiliary
            );
        }

        $fullTextGroup = [];

        $fullTextSearchFieldList = [];

        $hasFullTextSearch = false;

        if ($fullTextSearchData) {
            if ($this->fullTextRelevanceThreshold) {
                $fullTextGroup[] = [
                    $fullTextSearchData->getExpression() . '>=' => $this->fullTextRelevanceThreshold
                ];
            }
            else {
                $fullTextGroup[] = $fullTextSearchData->getExpression();
            }

            $fullTextSearchFieldList = $fullTextSearchData->getFieldList();

            $relevanceExpression = $fullTextSearchData->getExpression();

            $fullTextOrderType = $this->fullTextOrderType;

            $orderTypeMap = [
                'combined' => self::FT_ORDER_COMBINTED,
                'relevance' => self::FT_ORDER_RELEVANCE,
                'original' => self::FT_ORDER_ORIGINAL,
            ];

            $mOrderType = $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'fullTextSearchOrderType'
            ]);

            if ($mOrderType) {
                $fullTextOrderType = $orderTypeMap[$mOrderType];
            }

            $previousOrderBy = $queryBuilder->build()->getOrder();

            $hasOrderBy = !empty($previousOrderBy);

            if (!$hasOrderBy || $fullTextOrderType === self::FT_ORDER_RELEVANCE) {
                $queryBuilder->order([
                    [$relevanceExpression, 'desc']
                ]);
            }
            else if ($fullTextOrderType === self::FT_ORDER_COMBINTED) {
                $relevanceExpression =
                    'ROUND:(DIV:(' . $fullTextSearchData->getExpression() . ',' .
                    $this->fullTextOrderRelevanceDivider . '))';

                $newOrderBy = array_merge(
                    [
                        [$relevanceExpression, 'desc']
                    ],
                    $previousOrderBy,
                );

                $queryBuilder->order($newOrderBy);
            }

            $hasFullTextSearch = true;
        }

        foreach ($fieldList as $field) {
            if ($useFullTextSearch) {
                if (in_array($field, $fullTextSearchFieldList)) {
                    continue;
                }
            }

            if ($forceFullTextSearch) {
                continue;
            }

            $seed = $this->getSeed();

            $attributeType = null;

            if (strpos($field, '.') !== false) {
                list($link, $foreignField) = explode('.', $field);

                $foreignEntityType = $seed->getRelationParam($link, 'entity');

                $seed = $this->entityManager->getEntity($foreignEntityType);

                $queryBuilder->leftJoin($link);

                if ($seed->getRelationParam($link, 'type') === $seed::HAS_MANY) {
                    $queryBuilder->distinct();
                }

                $attributeType = $seed->getAttributeType($foreignField);
            }
            else {
                $attributeType = $seed->getAttributeType($field);

                if ($attributeType === 'foreign') {
                    $link = $seed->getAttributeParam($field, 'relation');

                    if ($link) {
                        $queryBuilder->leftJoin($link);
                    }
                }
            }

            if ($attributeType === 'int') {
                if (is_numeric($textFilter)) {
                    $orGroup[$field] = intval($textFilter);
                }

                continue;
            }

            if (!$skipWidlcards) {
                if (
                    mb_strlen($textFilter) >= $textFilterContainsMinLength
                    &&
                    (
                        $attributeType === 'text'
                        ||
                        in_array($field, $this->useContainsAttributeList)
                        ||
                        $attributeType === 'varchar' && $this->config->get('textFilterUseContainsForVarchar')
                    )
                ) {
                    $expression = '%' . $textFilter . '%';
                } else {
                    $expression = $textFilter . '%';
                }
            } else {
                $expression = $textFilter;
            }

            if ($fullTextSearchData) {
                if (!$useFullTextSearch) {
                    if (in_array($field, $fullTextSearchFieldList)) {
                        continue;
                    }
                }
            }

            $orGroup[$field . '*'] = $expression;
        }

        if (!$forceFullTextSearch) {
            $this->applyCustomToOrGroup($queryBuilder, $filter, $orGroup, $hasFullTextSearch);
        }

        if (!empty($fullTextGroup)) {
            $orGroup['AND'] = $fullTextGroup;
        }

        if (count($orGroup) === 0) {
            $queryBuilder->where([
                'id' => null
            ]);

            return;
        }

        $queryBuilder->where([
            'OR' => $orGroup
        ]);
    }

    protected function applyCustomToOrGroup(
        QueryBuilder $queryBuilder, string $filter, array &$orGroup, bool $hasFullTextSearch
    ) {
    }

    protected function getFullTextSearchData(string $filter, bool $isAuxiliaryUse = false) : ?FullTextSearchData
    {
        $composer = $this->fullTextSearchDataComposerFactory->create($this->entityType);

        $params = FullTextSearchDataComposerParams::fromArray([
            'isAuxiliaryUse' => $isAuxiliaryUse,
        ]);

        return $composer->compose($filter, $params);
    }

    protected function getSeed() : Entity
    {
        return $this->seed ?? $this->entityManager->getEntity($this->entityType);
    }
}
