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

namespace tests\integration\Espo\Core\Utils\Database;

class AutoIncrementFieldTest extends Base
{
    public function testColumn()
    {
        $column = $this->getColumnInfo('Case', 'number');

        $this->assertNotEmpty($column);
        $this->assertEquals('int', $column['DATA_TYPE']);
        $this->assertEquals('NO', $column['IS_NULLABLE']);
        $this->assertEquals('10', $column['NUMERIC_PRECISION']);
        $this->assertEquals('auto_increment', $column['EXTRA']);
        $this->assertEquals('UNI', $column['COLUMN_KEY']);
    }

    /*public function testColumnOnExistingTable()
    {
        $this->updateDefs('Test', 'testAutoIncrement', [
            'type' => 'autoincrement',
        ]);

        $column = $this->getColumnInfo('Test', 'testAutoIncrement');

        $this->assertNotEmpty($column);
        $this->assertEquals('int', $column['DATA_TYPE']);
        $this->assertEquals('NO', $column['IS_NULLABLE']);
        $this->assertEquals('10', $column['NUMERIC_PRECISION']);
        $this->assertEquals('auto_increment', $column['EXTRA']);
        $this->assertEquals('UNI', $column['COLUMN_KEY']);
    }*/
}