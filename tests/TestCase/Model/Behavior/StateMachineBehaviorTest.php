<?php

namespace StateMachine\Test\TestCase\Model\Behavior;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use StateMachine\Test\Model\Entity\Vehicle;
use StateMachine\Test\Model\Table\VehiclesTable;

class StateMachineBehaviorTest extends TestCase
{
    public $fixtures = [
        'plugin.StateMachine.Vehicle'
    ];

    /**
     * @var VehiclesTable
     */
    public $Vehicles;

    public $StateMachine;

    public function setUp(): void
    {
        parent::setUp();
        $this->Vehicles = TableRegistry::getTableLocator()->get('Vehicles', [
            'className' => 'StateMachine\Test\Model\Table\VehiclesTable'
        ]);
    }

    public function testGetAllTransitions()
    {
        $this->assertCount(9, $this->Vehicles->getAllTransitions());
    }

    public function testAvailableStates()
    {
        $this->assertCount(6, $this->Vehicles->getAvailableStates());
    }

    public function testInitialState()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()->first();
        $this->assertEquals("parked", $this->Vehicles->getCurrentState($entity));
        $this->assertEquals('parked', $this->Vehicles->getStates($entity, 'turn_off'));
    }

    public function testIsMethods()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();

        $this->assertEquals($this->Vehicles->is($entity, 'parked'), true);
        $this->assertEquals($this->Vehicles->is($entity, 'idling'), false);
        $this->assertEquals($this->Vehicles->is($entity, 'stalled'), false);

        $this->assertFalse($this->Vehicles->can($entity, 'shift_up'));

        $this->assertTrue($this->Vehicles->can($entity, 'ignite'));
        $this->Vehicles->transition($entity, 'ignite');
        $this->assertEquals("idling", $this->Vehicles->getCurrentState($entity));

        $this->assertTrue($this->Vehicles->can($entity, 'shift_up'));
        $this->assertFalse($this->Vehicles->can($entity, 'shift_down'));

        $this->assertTrue($this->Vehicles->is($entity, 'idling'));
        $this->assertFalse($this->Vehicles->can($entity, 'crash'));
        $this->Vehicles->transition($entity, 'shift_up');
        $this->Vehicles->transition($entity, 'crash');
        $this->assertEquals("stalled", $this->Vehicles->getCurrentState($entity));
        $this->assertTrue($this->Vehicles->is($entity, 'stalled'));
        $this->Vehicles->transition($entity, 'repair');
        $this->assertTrue($this->Vehicles->is($entity, 'parked'));
    }

    public function testOnMethods()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();

        $this->Vehicles->on('ignite', 'before', function (/** @noinspection PhpUnusedParameterInspection */
            $entity,
            $currentState,
            $previousState,
            $transition
        ) {
            $this->assertEquals("parked", $currentState);
            $this->assertNull($previousState);
            $this->assertEquals("ignite", $transition);
        });

        $this->Vehicles->on('ignite', 'after', function (/** @noinspection PhpUnusedParameterInspection */
            $entity,
            $currentState,
            $previousState,
            $transition
        ) {
            $this->assertEquals("idling", $currentState);
            $this->assertEquals("parked", $previousState);
            $this->assertEquals("ignite", $transition);
        });

        $this->Vehicles->transition($entity, 'ignite');
    }

    public function whenParked($entity)
    {
        $this->assertEquals('parked', $this->Vehicles->getCurrentState($entity));
    }

    public function testWhenMethods()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();

        $this->Vehicles->when('stalled', function ($entity) {
            $this->assertEquals("stalled", $this->Vehicles->getCurrentState($entity));
        });

        $this->Vehicles->when('parked', [$this, 'whenParked']);

        $this->Vehicles->transition($entity, 'ignite');
        $this->Vehicles->transition($entity, 'shift_up');
        $this->Vehicles->transition($entity, 'crash');
        $this->Vehicles->transition($entity, 'repair');
    }

    public function testBubble()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();

        $this->Vehicles->on('ignite', 'before', function ($entity) {
            $this->assertEquals("parked", $this->Vehicles->getCurrentState($entity));
        }, false);

        $this->Vehicles->on('ignite', 'before', function () {
            // this should never be called
            $this->assertTrue(false);
        });

        $this->Vehicles->transition($entity, 'ignite');
    }

    public function testInvalidTransition()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();

        $this->assertFalse($this->Vehicles->getStates($entity, 'foobar'));
        $this->assertFalse($this->Vehicles->getStates($entity, 'baz'));
        $this->assertFalse($this->Vehicles->transition($entity, 'baz'));
    }

    public function testVehicleTitle()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['title' => 'Opel Astra'])
            ->first();

        $this->assertEquals("Opel Astra", $entity->title);
        $this->assertEquals("idling", $this->Vehicles->getCurrentState($entity));
        $this->assertTrue($this->Vehicles->transition($entity, 'shiftUp'));
        $this->assertEquals("first_gear", $this->Vehicles->getCurrentState($entity));

        $entity = $this->Vehicles->find()
            ->where(['title' => 'Nissan Leaf'])
            ->first();

        $this->assertEquals("Nissan Leaf", $entity->title);
        $this->assertEquals("stalled", $this->Vehicles->getCurrentState($entity));
        $this->assertTrue($this->Vehicles->can($entity, 'Repair'));
        $this->assertTrue($this->Vehicles->transition($entity, 'Repair'));
        $this->assertEquals("parked", $this->Vehicles->getCurrentState($entity));
    }

    public function testCreateVehicle()
    {
        $entity = $this->Vehicles->newEntity([]);
        $entity->title = 'Toybota';
        $this->Vehicles->save($entity);
        $this->assertEquals(5, $entity->id);
        $this->assertEquals($this->Vehicles->initialState, $entity->state);
        $this->assertEquals($this->Vehicles->initialState, $this->Vehicles->getCurrentState($entity));
    }

    public function testToDot()
    {
        $expectedOutput = <<<EOT
digraph finite_state_machine {
	rankdir=LR
	fontsize=12
	node [shape = circle];
	parked -> idling [ label = "ignite" ];
	stalled -> stalled [ label = "ignite" ];
	idling -> parked [ label = "park" ];
	first_gear -> parked [ label = "park" ];
	idling -> first_gear [ label = "shift_up" ];
	first_gear -> second_gear [ label = "shift_up" ];
	second_gear -> third_gear [ label = "shift_up" ];
	first_gear -> idling [ label = "shift_down" ];
	second_gear -> first_gear [ label = "shift_down" ];
	third_gear -> second_gear [ label = "shift_down" ];
	first_gear -> stalled [ label = "crash" ];
	second_gear -> stalled [ label = "crash" ];
	third_gear -> stalled [ label = "crash" ];
	stalled -> parked [ label = "repair" ];
	first_gear -> idling [ label = "idle" ];
	all -> parked [ label = "turn_off" ];
}
EOT;
        $this->assertEquals($expectedOutput, $this->Vehicles->toDot());
    }

    public function testOnStateChange()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->first();
        $this->assertTrue($this->Vehicles->transition($entity, 'ignite'));
        $expected = [
            'onBeforeTransition',
            'onAfterTransition',
            'onStateIdling',
            'onStateChange',
        ];
        $this->assertEquals($expected, $this->Vehicles->events);
    }

    public function testFilledFields()
    {
        /** @var Vehicle $entity */
        $entity = $this->Vehicles->find()
            ->where(['state' => 'parked'])
            ->firstOrFail();
        $before = $entity->toArray();
        $this->assertTrue($this->Vehicles->transition($entity, 'ignite'));
        $this->assertNotEquals($entity->get('state'), $before['state']);
        $this->assertEquals($entity->get('previous_state'), $before['state']);
        $this->assertTrue($this->Vehicles->transition($entity, 'shift_up'));
        $this->assertNotEmpty($entity->get('last_state_update'));
        $this->assertNotEmpty($entity->get('states_history'));
        $history = json_decode($entity->get('states_history'));
        $this->assertEquals($entity->get('previous_state'), $history[0]->state);
        $this->assertEquals($entity->get('last_state_update'), new \DateTime($history[1]->date));
    }

    public function testTransitionAll()
    {
        $beforeParked = $this->Vehicles->find()->where(['state' => 'parked'])->count();
        $this->assertGreaterThanOrEqual(2, $beforeParked);

        $count = $this->Vehicles->transitionAll('ignite', ['state' => 'parked']);

        $afterParked = $this->Vehicles->find()->where(['state' => 'parked'])->count();
        $this->assertEquals(0, $afterParked);
        $this->assertEquals($beforeParked - $count, $afterParked);
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->Vehicles);
    }

    public function __destruct()
    {
        if (is_file(DATABASE_TEST_SQLITE)) {
            unlink(DATABASE_TEST_SQLITE);
        }
    }
}
