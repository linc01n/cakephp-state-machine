<?php
namespace StateMachine\Shell;

use Cake\Console\Shell;
use Cake\Utility\Inflector;

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

    public function main($model = null, $filename = null)
    {
        if (!$model && !$filename) {
            $this->out('bin/cake dot generate ModelName name.png');
        } else {
            return $this->generate($model, $filename);
        }
    }

    public function generate($model, $filename = null)
    {
        $this->out('Generate files');

        $this->out('Load Model:' . $model);
        $this->Model = $this->loadModel($model);

        // generate all roles
        $dot = $this->Model->toDot();

        if (empty($filename)) {
            $filename = Inflector::underscore($this->Model->getAlias()).'-states.png';
        }
        $dir = dirname($filename);
        if ($dir !== '.' && is_dir($dir) && is_writable($dir)) {
            $dest = $dir . DS . basename($filename);
        } else {
            $dest = TMP . $filename;
        }
        $this->_generatePng($dot, $dest);
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
        exec($command." 2>&1", $output, $code);
        if ($code === 0) {
            $this->success($destFile);
        } else {
            $this->err(implode(PHP_EOL, $output));
        }
    }
}
