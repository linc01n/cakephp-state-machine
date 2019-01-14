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


    /**
     * Gets the option parser instance and configures it.
     *
     * By overriding this method you can configure the ConsoleOptionParser before returning it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     * @link https://book.cakephp.org/3.0/en/console-and-shells.html#configuring-options-and-generating-help
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->setDescription([
            "--------------------------------------------------------------------",
            "StateMachine DotShell",
            "--------------------------------------------------------------------",
        ]);
        $parser->addArgument('model', [
            'required' => true,
            'help' => "Model name"
        ]);
        $parser->addArgument('filename', [
            'help' => "output filename"
        ]);
        $parser->addOption('output-dir', [
            'help' => "output directory",
            'default' => rtrim(TMP, DS),
            'short' => 'o',
        ]);
        return $parser;
    }

    public function main($model, $filename = null)
    {
        return $this->generate($model, $filename);
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
            $dest = $this->param('output-dir') . DS . $filename;
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
