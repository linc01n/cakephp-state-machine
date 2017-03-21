<?php

namespace StateMachine\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class VehicleFixture extends TestFixture
{

	public $fields = array(
		'id' => array('type' => 'integer'),
		'title' => array('type' => 'string', 'length' => 255, 'null' => false),
		'state' => array('type' => 'string', 'length' => 255, 'null' => true),
		'previous_state' => array('type' => 'string', 'length' => 255, 'null' => true),
        '_constraints' => array(
            'primary' => array('type' => 'primary', 'columns' => array('id'))
        )
	);

	public $records = array(
		array('title' => 'Audi Q4', 'state' => 'parked'),
		array('title' => 'Toyota Yaris', 'state' => 'parked'),
		array('title' => 'Opel Astra', 'state' => 'idling', 'previous_state' => 'parked'),
		array('title' => 'Nissan Leaf', 'state' => 'stalled', 'previous_state' => 'third_gear'),
	);
}
