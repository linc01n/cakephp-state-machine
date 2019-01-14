<?php
namespace StateMachine\Shell;

use Cake\Console\Shell;

/**
 * Dot Shell Class
 *
 * Simple dot generation of PNG files from state-machine
 */
class DotShell extends Shell
{

    /**
     * @var \StateMachine\Model\Behavior\StateMachineBehavior
     */
    public $Model;

    public function main()
    {
        $this->out('bin/cake dot generate ModelName name.png');
    }

    public function generate()
    {
        $this->out('Generate files');

        $name = $this->args[0];

        $this->out('Load Model:' . $name);
        $this->loadModel($name);
        $this->Model = $this->{$name};

        // generate all roles
        $dot = $this->Model->toDot();
        $this->_generatePng($dot, TMP . $this->args[1]);
    }

    /**
     * This function generates a png file from a given dot. A dot can be generated wit *toDot functions
     * @param  string $dot The contents for graphviz
     * @param  string $destFile Name with full path to where file is to be created
     * @return bool|null|string returns whatever shell_exec returns
     */
    protected function _generatePng($dot, $destFile)
    {
        if (!isset($dot)) {
            return false;
        }
        $dotExec = "echo '%s' | dot -Tpng -o%s";
        $command = sprintf($dotExec, $dot, $destFile);
        return shell_exec($command);
    }
}
