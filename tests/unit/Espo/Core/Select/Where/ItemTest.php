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

namespace tests\unit\Espo\Core\Select\Where;

use Espo\Core\{
    Select\Where\Item,
};

use InvalidArgumentException;

class ItemTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
    }

    public function testFromArray()
    {
        $item = Item::fromArray([
            'type' => 'equals',
            'attribute' => 'test',
            'value' => 'testValue',
        ]);

        $this->assertEquals('equals', $item->getType());
        $this->assertEquals('test', $item->getAttribute());
        $this->assertEquals('testValue', $item->getValue());
        $this->assertFalse($item->isDateTime());
        $this->assertEquals(null, $item->getTimeZone());

        $item = Item::fromArray([
            'type' => 'equals',
            'attribute' => 'test',
            'value' => 1,
        ]);

        $this->assertEquals('equals', $item->getType());
        $this->assertEquals('test', $item->getAttribute());
        $this->assertEquals(1, $item->getValue());

        $item = Item::fromArray([
            'type' => 'equals',
            'attribute' => 'test',
            'value' => 'testValue',
            'dateTime' => true,
            'timeZone' => 'Europe/London',
        ]);

        $this->assertTrue($item->isDateTime());
        $this->assertEquals('Europe/London', $item->getTimeZone());
    }

    public function testEmpty()
    {
        $this->expectException(InvalidArgumentException::class);

        $item = Item::fromArray([
        ]);
    }

    public function testEmptyAttribute1()
    {
        $this->expectException(InvalidArgumentException::class);

        $item = Item::fromArray([
            'type' => 'equals',
        ]);
    }

    public function testEmptyAttribute2()
    {
        $item = Item::fromArray([
            'type' => 'and',
            'value' => [],
        ]);

        $this->assertNotNull($item);
    }

    public function testEmptyType()
    {
        $this->expectException(InvalidArgumentException::class);

        $item = Item::fromArray([
            'attribute' => 'test',
        ]);
    }

    public function testNonExistingParam()
    {
        $this->expectException(InvalidArgumentException::class);

        $params = Item::fromArray([
            'bad' => 'd',
        ]);
    }

    public function testGetRaw1()
    {
        $raw = [
            'type' => 'and',
            'value' => [],
        ];

        $item = Item::fromArray($raw);

        $result = $item->getRaw();

        $this->assertEquals($raw, $result);
    }

    public function testGetRaw2()
    {
        $raw = [
            'type' => 'euqls',
            'attribute' => 'test',
            'value' => '2020-12-12',
            'dateTime' => true,
            'timeZone' => 'UTC',
        ];

        $item = Item::fromArray($raw);

        $result = $item->getRaw();

        $this->assertEquals($raw, $result);
    }
}