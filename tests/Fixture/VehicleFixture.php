<?php

namespace StateMachine\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class VehicleFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string', 'length' => 255, 'null' => false],
        'state' => ['type' => 'string', 'length' => 255, 'null' => true],
        'previous_state' => ['type' => 'string', 'length' => 255, 'null' => true],
        'last_state_update' => ['type' => 'timestamp', 'null' => true],
        'states_history' => ['type' => 'text', 'null' => true],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']]
        ]
    ];

    public $records = [
        ['title' => 'Audi Q4', 'state' => 'parked'],
        ['title' => 'Toyota Yaris', 'state' => 'parked'],
        ['title' => 'Opel Astra', 'state' => 'idling', 'previous_state' => 'parked'],
        ['title' => 'Nissan Leaf', 'state' => 'stalled', 'previous_state' => 'third_gear'],
    ];
}
