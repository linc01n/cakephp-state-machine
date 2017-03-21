CakePHP 3 State Machine
=====================
[![Build Status](https://travis-ci.org/davidsteinsland/cakephp-state-machine.png?branch=master)](https://travis-ci.org/davidsteinsland/cakephp-state-machine) [![Coverage Status](https://coveralls.io/repos/davidsteinsland/cakephp-state-machine/badge.png?branch=master)](https://coveralls.io/r/davidsteinsland/cakephp-state-machine?branch=master) [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/davidsteinsland/cakephp-state-machine/badges/quality-score.png?s=7d6d7a43f47401c3a4fda69d799c9d671a8659e3)](https://scrutinizer-ci.com/g/davidsteinsland/cakephp-state-machine/) [![Latest Stable Version](https://poser.pugx.org/davidsteinsland/cakephp-state-machine/v/stable.png)](https://packagist.org/packages/davidsteinsland/cakephp-state-machine) [![Total Downloads](https://poser.pugx.org/davidsteinsland/cakephp-state-machine/downloads.png)](https://packagist.org/packages/davidsteinsland/cakephp-state-machine)

Documentation is not finished yet either. See the tests if you want to learn something, as all aspects of the state machine is tested there.

## What is a State Machine?
http://en.wikipedia.org/wiki/State_machine

## Installation
First you need to alter the tables of the models you want to use StateMachine:
```sql
ALTER TABLE `vehicle` ADD `state` VARCHAR(50);
ALTER TABLE `vehicle` ADD `previous_state` VARCHAR(50);
```

## Features
- Callbacks on states and transitions
- Custom methods may be added to your model
- `is($entity, $state)`, `can($entity, $transition)`, `on($transition, 'before|after', callback)` and `when($state, callback)` methods allows you to control the whole flow. `transition($entity, $transition)` is used to move between two states.
- Graphviz
- (removed: Roles and rules, feel free to commit it)

### Callbacks
You can add callbacks that will fire before/after a transition, and before/after a state change. This can either be done manually with `$this->on('mytransition', 'before', funtion() {})`, or you can add a method to your model:

```php
public function onBeforeTransition($entity, $currentState, $previousState, $transition) {
    // will fire on all transitions
}

public function onAfterIgnite($entity, $currentState, $previousState, $transition) {
    // will fire after the ignite transition
}
```

The state callbacks are a little different:

```php
public function onStateChange($entity, $newState) {
    // will fire on all state changes
}

public function onStateIdling($entity, $newState) {
    // will fire on the idling state
}
```


## Naming conventions
- Transitions and states in `$transitions` should be **lowercased** and **underscored**. The method names are in turn camelized.
  
  Example:

```
    shift_up   => can($entity, 'ShiftUp') => transition($entity, 'ShiftUp')
    first_gear => is($entity, 'FirstGear')
```

## How to Use
```php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\Entity;

class VehiclesTable extends Table
{
    public function initialize(array $config)
    {
        $this->addBehavior('StateMachine.StateMachine');
        $this->on('ignite', 'after', function(Entity $entity, $prevState, $nextState, $transition) {
            // the car just ignited!
        });
    }

    public $initialState = 'parked';

    public $transitions = array(
            'ignite' => array(
                    'parked' => 'idling',
                    'stalled' => 'stalled'
            ),
            'park' => array(
                    'idling' => 'parked',
                    'first_gear' => 'parked'
            ),
            'shift_up' => array(
                    'idling' => 'first_gear',
                    'first_gear' => 'second_gear',
                    'second_gear' => 'third_gear'
            ),
            'shift_down' => array(
                    'first_gear' => 'idling',
                    'second_gear' => 'first_gear',
                    'third_gear' => 'second_gear'
            ),
            'crash' => array(
                    'first_gear' => 'stalled',
                    'second_gear' => 'stalled',
                    'third_gear' => 'stalled'
            ),
            'repair' => array(
                    'stalled' => 'parked'
            ),
            'idle' => array(
                    'first_gear' => 'idling'
            ),
            'turn_off' => array(
                    'all' => 'parked'
            )
    );

    // a shortcut method for checking if the vehicle is moving
    public function isMoving(Entity $entity) {
        return in_array($this->getCurrentState($entity), array('first_gear', 'second_gear', 'third_gear'));
    }

}
```

```php
class Controller .... {
    public function method() {
        $this->loadModel('Vehicles');
        $entity = $this->Vehicles->newEntity();
        $entity->title = 'Toybota';
        $this->Vehicles->save($entity);

        // $this->Vehicles->getCurrentState($entity) == 'parked'
	if ($this->Vehicles->can($entity, 'Ignite')) {
       	 	$this->Vehicles->transition($entity, 'Ignite');
       	 	$this->Vehicles->transition($entity, 'shiftUp');
        	// $this->Vehicles->getCurrentState($entity) == 'first_gear'
        }
    }
}
```

## Graphviz
Here's how to state machine of the Vehicle would look like if you saved:
```php
$table->toDot()
```
into `fsm.gv` and ran:
```sh
dot -Tpng -ofsm.png fsm.gv
```
![](fsm.png)
