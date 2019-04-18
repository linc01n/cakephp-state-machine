<?php
/**
 * StateMachine\Model\Behavior\Exception\TransitionFailedException
 */

namespace StateMachine\Model\Behavior\Exception;

use Cake\Core\Exception\Exception;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;

/**
 * Used when a strict transition fails
 */
class TransitionFailedException extends Exception
{

    /**
     * The entity on which the transition operation failed
     *
     * @var \Cake\Datasource\EntityInterface
     */
    protected $_entity;

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Entity %s failure. Unable to apply "%s" transition to "%s"';

    /**
     * Constructor.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity on which the persistence operation failed
     * @param string|array $message Either the string of the error message, or an array of attributes
     *   that are made available in the view, and sprintf()'d into Exception::$_messageTemplate
     * @param int $code The code of the error, is also the HTTP status code for the error.
     * @param \Exception|null $previous the previous exception.
     */
    public function __construct(EntityInterface $entity, $message, $code = null, $previous = null)
    {

        parent::__construct([$entity->get('id'), $message[0], $entity->get('state')], $code, $previous);
    }
}