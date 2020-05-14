<?php
/**
 * StateMachine\Model\Behavior\StateMachineBehavior
 */

namespace StateMachine\Model\Behavior;

use ArrayObject;
use BadMethodCallException;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\Utility\Inflector;

/**
 * StateMachineBehavior
 *
 * A finite state machine is a machine that cannot move between states unless
 * a specific transition fired. It has a specified amount of legal directions it can
 * take from each state. It also supports state listeners and transition listeners.
 *
 * @author David Steinsland
 * @author Ludovic Ganée
 */
class StateMachineBehavior extends Behavior
{
    /**
     * Default config
     *
     * These are merged with user-provided config when the behavior is used.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'transition_listeners' => [
            'transition' => [
                'before' => [],
                'after' => []
            ]
        ],
        'state_listeners' => [],
        'methods' => [],
        'fields' => [
            'state' => 'state',
            'previous_state' => 'previous_state',
            'last_state_update' => 'last_state_update',
            'states_history' => 'states_history',
        ],
    ];

    /**
     * Table using this behavior
     *
     * @var Table
     */
    protected $_table;

    /**
     * For adding Table
     *
     * @param Table $table
     * @param array $config
     */
    public function __construct(Table $table, array $config)
    {
        parent::__construct($table, $config);
        $this->_table = $table;
    }

    /**
     * Sets up all the methods that builds up the state machine.
     * StateMachine->is<State>		    i.e. StateMachine->isParked()
     * StateMachine->can<Transition>	i.e. StateMachine->canShiftGear()
     * StateMachine-><transition>		i.e. StateMachine->shiftGear();
     *
     * @param array $config The config for this behavior.
     * @return void
     */
    public function initialize(array $config): void
    {
        if (!property_exists($this->_table, 'transitions')) {
            throw new \InvalidArgumentException(
                'Missing attribute "transitions" '
                . 'in "'.$this->_table->getAlias() . 'Table" '
                . 'for using StateMachineBehavior'
            );
        }

        foreach ($this->_table->transitions as $states) {
            foreach ($states as $stateFrom => $stateTo) {
                $this->_addAvailableState(Inflector::camelize($stateFrom));
                $this->_addAvailableState(Inflector::camelize($stateTo));
            }
        }
    }

    /**
     * Array of all configured states. Initialized by self::initialize()
     * @var array
     */
    protected $_availableStates = [];

    /**
     * Add a value to $this->_availableStates
     *
     * @param string $state
     */
    protected function _addAvailableState($state)
    {
        if ($state != 'All' && !in_array($state, $this->_availableStates)) {
            $this->_availableStates[] = Inflector::camelize($state);
        }
    }

    /**
     * Add initial State on new entities
     *
     * @param	Event           $event
     * @param	EntityInterface $entity
     * @param	ArrayObject     $options	Options passed to save
     * @return	boolean
     */
    public function beforeSave(/** @noinspection PhpUnusedParameterInspection */
        Event $event,
        EntityInterface $entity,
        ArrayObject $options
    ) {
        if (empty($entity->get($this->getConfig('fields.state'))) && $entity->isNew()) {
            $entity->set($this->getConfig('fields.state'), $this->_table->initialState);
        }

        return true;
    }

    /**
     * returns all transitions defined in model
     *
     * @return array array of transitions
     * @author Frode Marton Meling
     */
    public function getAllTransitions()
    {
        return array_keys((array)$this->_table->transitions);
    }

    /**
     * Returns an array of all configured states
     *
     * @return array
     */
    public function getAvailableStates()
    {
        return $this->_availableStates;
    }

    /**
     * Allows moving from one state to another.
     * {{{
     * $this->Entity->transition('shift_gear');
     * // or
     * $this->Entity->shiftGear();
     * }}}
     *
     * @param EntityInterface $entity
     * @param string $transition The transition beingda initiated
     * @return boolean success
     */
    public function transition(EntityInterface $entity, $transition)
    {
        $transition = Inflector::underscore($transition);
        $state = $this->getStates($entity, $transition);
        if (!$state) {
            return false;
        }

        $this->_callTransitionListeners($entity, $transition, 'before');

        $entity->set($this->getConfig('fields.previous_state'), $this->getCurrentState($entity));
        $entity->set($this->getConfig('fields.state'), $state);

        $datetime = new \DateTime;
        $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $entity->set($this->getConfig('fields.last_state_update'), $datetime);

        if ($history = $entity->get($this->getConfig('fields.states_history'))) {
            $history = json_decode($history, true);
        } else {
            $history = [];
        }

        $history[] = [
            'date' => $datetime->format('Y-m-d\TH:i:s.uO'),
            'state' => $state,
        ];
        $entity->set($this->getConfig('fields.states_history'), json_encode($history));

        $this->_callTransitionListeners($entity, $transition, 'after');

        $stateListeners = [];
        $config = $this->getConfig('state_listeners');
        if (isset($config[$state])) {
            $stateListeners = $config[$state];
        }

        foreach ([
            'onState' . Inflector::camelize($state),
            'onStateChange'
         ] as $method) {
            if (method_exists($this->_table, $method)) {
                $stateListeners[] = [$this->_table, $method];
            }
        }

        foreach ($stateListeners as $cb) {
            $cb($entity, $state);
        }

        return true;
    }

    /**
     * Allows moving from one state to another.
     * @param EntityInterface $entity
     * @param string $transition
     * @return $this
     * @throws Exception\TransitionFailedException if there is no change of state
     */
    public function transitionOrFail(EntityInterface $entity, $transition)
    {
        if (!$this->transition($entity, $transition)) {
            throw new Exception\TransitionFailedException($entity, [$transition]);
        }
        return $this;
    }

    /**
     * Checks whether the state machine is in the given state
     *
     * @param EntityInterface $entity
     * @param string $state The state being checked
     * @return boolean whether or not the state machine is in the given state
     * @throws BadMethodCallException when method does not exists
     */
    public function is(EntityInterface $entity, $state)
    {
        return $this->getCurrentState($entity) === $this->_deFormalizeMethodName($state);
    }

    /**
     * Checks whether or not the machine is able to perform transition, in its current state
     *
     * @param EntityInterface $entity
     * @param string $transition The transition being checked
     * @return boolean whether or not the machine can perform the transition
     * @throws BadMethodCallException when method does not exists
     */
    public function can(EntityInterface $entity, $transition)
    {
        $transition = $this->_deFormalizeMethodName($transition);

        if (!$this->getStates($entity, $transition)) {
            return false;
        }

        return true;
    }

    /**
     * Registers a callback function to be called when the machine leaves one state.
     * The callback is fired either before or after the given transition.
     *
     * @param	string	$transition		The transition to listen to
     * @param	string	$triggerType	Either before or after
     * @param	callable	$cb				The callback function that will be called
     * @param	Boolean	$bubble			Whether or not to bubble other listeners
     */
    public function on($transition, $triggerType, callable $cb, $bubble = true)
    {
        $config = $this->getConfig('transition_listeners');
        $config[Inflector::underscore($transition)][$triggerType][] = [
            'cb' => $cb,
            'bubble' => $bubble
        ];
        $this->setConfig('transition_listeners', $config);
    }

    /**
     * Registers a callback that will be called when the state machine enters the given
     * state.
     *
     * @param	string	$state	The state which the machine should enter
     * @param	callable	$cb		The callback function that will be called
     * @return self
     */
    public function when($state, callable $cb)
    {
        $config = $this->getConfig('state_listeners.'.Inflector::underscore($state));
        $config[] = $cb;
        $this->setConfig('state_listeners.'.Inflector::underscore($state), $config);
        return $this;
    }

    /**
     * Returns the states the machine would be in, after the given transition
     *
     * @param	Entity	$entity
     * @param	string	$transition	The transition name
     * @return	mixed				False if the transition doesnt yield any states, or an array of states
     */
    public function getStates(EntityInterface $entity, $transition)
    {
        if (!isset($this->_table->transitions[$transition])) {
            // transition doesn't exist
            return false;
        }

        // get the states the machine can move from and to
        $states = $this->_table->transitions[$transition];
        $currentState = $this->getCurrentState($entity);

        if (isset($states[$currentState])) {
            return $states[$currentState];
        }

        if (isset($states['all'])) {
            return $states['all'];
        }

        return false;
    }

    /**
     * Returns the current state of the machine
     *
     * @param	Entity	$entity
     * @return	string			The current state of the machine
     */
    public function getCurrentState(EntityInterface $entity)
    {
        return $entity->get($this->getConfig('fields.state')) ?: $this->_table->initialState;
    }

    /**
     * Returns the previous state of the machine
     *
     * @param	Entity	$entity
     * @return	string			The previous state of the machine
     */
    public function getPreviousState(EntityInterface $entity)
    {
        return $entity->get($this->getConfig('fields.previous_state'));
    }

    /**
     * Simple method to return contents for a GV file, that
     * can be made into graphics by:
     * {{{
     * dot -Tpng -ofsm.png fsm.gv
     * }}}
     * Assuming that the contents are written to the file fsm.gv
     *
     * @return	string			The contents of the graphviz file
     */
    public function toDot()
    {
        $digraph = <<<EOT
digraph finite_state_machine {
	rankdir=LR
	fontsize=12
	node [shape = circle];

EOT;

        foreach ($this->_table->transitions as $transition => $states) {
            foreach ($states as $stateFrom => $stateTo) {
                $digraph .= sprintf("\t%s -> %s [ label = \"%s\" ];\n", $stateFrom, $stateTo, $transition);
            }
        }

        return $digraph . "}";
    }

    /**
     * Calls transition listeners before or after a particular transition.
     * Special model methods are also called, if they exist:
     * - onBeforeTransition
     * - onAfterTransition
     * - onBefore<Transition>	i.e. onBeforePark()
     * - onAfter<Transition>	i.e. onAfterPark()
     *
     * @param	Entity	$entity
     * @param	string	$transition		The transition name
     * @param	string	$triggerType	Either before or after
     */
    protected function _callTransitionListeners(EntityInterface $entity, $transition, $triggerType = 'after')
    {
        $transitionListeners = $this->getConfig('transition_listeners');
        $listeners = $transitionListeners['transition'][$triggerType];

        if (isset($transitionListeners[$transition][$triggerType])) {
            $listeners = array_merge($transitionListeners[$transition][$triggerType], $listeners);
        }

        foreach ([
            'on' . Inflector::camelize($triggerType . 'Transition'),
            'on' . Inflector::camelize($triggerType . $transition)
         ] as $method) {
            if (method_exists($this->_table, $method)) {
                $listeners[] = [
                    'cb' => [$this->_table, $method],
                    'bubble' => true
                ];
            }
        }

        $currentState = $this->getCurrentState($entity);
        $previousState = $this->getPreviousState($entity);

        foreach ($listeners as $cb) {
            $cb['cb']($entity, $currentState, $previousState, $transition);

            if (!$cb['bubble']) {
                break;
            }
        }
    }

    /**
     * Deformalizes a method name, removing 'can' and 'is' as well as underscoring
     * the remaining text.
     *
     * @param	string	$name	The model name
     * @return	string			The deformalized method name
     */
    protected function _deFormalizeMethodName($name)
    {
        return Inflector::underscore(preg_replace('#^(can|is)#', '', $name));
    }

    /**
     * Do $transition to all rows returned in $conditions
     *
     * @param string $transition The transition name
     * @param array  $conditions updateAll conditions
     * @return int Count Returns the affected rows.
     */
    public function transitionAll($transition, $conditions)
    {
        if (!isset($this->_table->transitions[$transition])) {
            // transition doesn't exist
            return false;
        }
        $stateField = $this->getConfig('fields.state');
        $count = 0;
        foreach ($this->_table->transitions[$transition] as $stateFrom => $stateTo) {
            $count += (int)$this->_table->updateAll(
                [$stateField => Inflector::underscore($stateTo)],
                $conditions + [$stateField => Inflector::underscore($stateFrom)]
            );
        }
        return $count;
    }
}
