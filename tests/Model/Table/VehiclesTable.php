<?php

namespace StateMachine\Test\Model\Table;

use Cake\ORM\Table;
use StateMachine\Model\Behavior\StateMachineBehavior;

/**
 * Class VehiclesTable
 * @package StateMachine\Test\Model\Table
 * @mixin StateMachineBehavior
 */
class VehiclesTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setTable('vehicles');
        $this->addBehavior('StateMachine.StateMachine');
        $this->setEntityClass('StateMachine\Test\Model\Entity\Vehicle');
    }

    public $initialState = 'parked';

    public $transitions = [
        'ignite' => [
            'parked' => 'idling',
            'stalled' => 'stalled'
        ],
        'park' => [
            'idling' => 'parked',
            'first_gear' => 'parked'
        ],
        'shift_up' => [
            'idling' => 'first_gear',
            'first_gear' => 'second_gear',
            'second_gear' => 'third_gear'
        ],
        'shift_down' => [
            'first_gear' => 'idling',
            'second_gear' => 'first_gear',
            'third_gear' => 'second_gear'
        ],
        'crash' => [
            'first_gear' => 'stalled',
            'second_gear' => 'stalled',
            'third_gear' => 'stalled'
        ],
        'repair' => [
            'stalled' => 'parked'
        ],
        'idle' => [
            'first_gear' => 'idling'
        ],
        'turn_off' => [
            'all' => 'parked'
        ],
        'baz' => []
    ];
    public $events = [];

    public function onBeforeTransition()
    {
        $this->events[] = __FUNCTION__;
    }
    public function onStateChange()
    {
        $this->events[] = __FUNCTION__;
    }
    public function onAfterTransition()
    {
        $this->events[] = __FUNCTION__;
    }
    public function onStateIdling()
    {
        $this->events[] = __FUNCTION__;
    }
}
