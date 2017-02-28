<?php

namespace DockerCompose\Manager;

use DockerCompose\Exception\ComposeFileNotFoundException;
use DockerCompose\Exception\DockerHostConnexionErrorException;
use DockerCompose\Exception\DockerInstallationMissingException;
use DockerCompose\Exception\NoSuchServiceException;
use DockerCompose\ComposeFileCollection;
use mikehaertl\shellcommand\Command;
use Exception;

/**
 * DockerCompose\Manager\ComposeManager
 */
class ComposeManager
{

    /**
     * @var array environment variables for command
     */
    public $env = [];

    /**
     * @var array environment variables for command
     */
    public $cwd = null;

    /**
     * Start service containers
     *
     * @param mixed $composeFiles The compose files names
     */
    public function start($composeFiles = array())
    {
        return $this->processResult(
            $this->execute(
                $this->formatCommand('up -d', $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Stop service containers
     *
     * @param mixed $composeFiles The compose files names
     */
    public function stop($composeFiles = array())
    {
        return $this->processResult(
            $this->execute(
                $this->formatCommand('stop', $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Stop service containers
     *
     * @param mixed   $composeFiles  The compose files names
     * @param boolean $force         If the remove need to be force (default=false)
     * @param boolean $removeVolumes If we need to remove the volumes (default=false)
     */
    public function remove($composeFiles = array(), $force = false, $removeVolumes = false)
    {
        $command = 'rm --force';

        if ($removeVolumes) {
            $command .= ' -v';
        }

        return $this->processResult(
            $this->execute(
                $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
            )
        );
    }

     /**
     * Stop service containers
     *
     * @param mixed   $composeFiles  The compose files names
     * @param string  $signal        Optionnal to precise SIGNAL to send to the container for SIGKILL replacement.
     */
    public function kill($composeFiles = array(), $signal = 'SIGKILL')
    {
        $command = 'kill';

        if ($signal !== 'SIGKILL') {
            $command .= ' -s ' . $signal;
        }

        return $this->processResult(
            $this->execute(
                $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Build service images
     *
     * @param mixed   $composeFiles  The compose files names
     * @param boolean $pull          If we want attempt to pull a newer version of the from image
     * @param boolean $forceRemove   If we want remove the intermediate containers
     * @param bollean $cache         If we can use the cache when building the image
     */
    public function build($composeFiles = array(), $pull = true, $forceRemove = false, $cache = true)
    {
        $command = 'build';

        if ($pull) {
            $command .= ' --pull';
        }

        if ($forceRemove) {
            $command .= ' --force-rm';
        }

        if (!$cache) {
            $command .= ' --no-cache';
        }

        return $this->processResult(
            $this->execute(
                $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Pull service images
     *
     * @param mixed   $composeFiles  The compose files names
     */
    public function pull($composeFiles = array())
    {
        $command = 'pull';

        return $this->processResult(
            $this->execute(
                $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
            )
        );
    }


    /**
     * Restart running containers
     *
     * @param mixed   $composeFiles  The compose files names
     * @param integer $timeout       If we want attempt to pull a newer version of the from image
     */
    public function restart($composeFiles = array(), $timeout = 10)
    {
        $command = 'restart';

        if ($timeout != 10) {
            $command .= ' --timeout='.$timeout;
        }

        return $this->processResult(
            $this->execute(
                $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Run service with command
     *
     * @param string $service Service name
     * @param string $command Command to pass to service
     * @param mixed   $composeFiles  The compose files names
     */
    public function run($service, $command, $composeFiles = array())
    {
        $command = 'run --rm ' . $service . ' ' . $command;
        $result = $this->execute(
            $this->formatCommand($command, $this->createComposeFileCollection($composeFiles))
        );

        if ($result['code'] == 1 && strpos($result['output'], 'service') != false) {
            throw new NoSuchServiceException($result['output']);
        }

        return $this->processResult($result);
    }

    /**
     * List containers
     *
     * @param mixed $composeFiles The compose files names
     */
    public function ps($composeFiles = array())
    {
        return $this->processResult(
            $this->execute(
                $this->formatCommand('ps', $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * Show configuration (yaml)
     *
     * @param mixed $composeFiles The compose files names
     */
    public function config($composeFiles = array())
    {
        return $this->processResult(
            $this->execute(
                $this->formatCommand('config', $this->createComposeFileCollection($composeFiles))
            )
        );
    }

    /**
     * List IP containers
     *
     * @param mixed $composeFiles The compose files names
     */
    public function ips($composeFiles = array())
    {
        $command = $this->formatCommand('ps', $this->createComposeFileCollection($composeFiles));

        $command = 'for CONTAINER in $(' . $command . ' -q); ';
        $command .= 'do echo "$(docker inspect --format \' {{ .Name }} \' $CONTAINER)\t';
        $command .= '$(docker inspect --format \' {{ .NetworkSettings.IPAddress }} \' $CONTAINER)"; done';

        return $this->processResult($this->execute($command));
    }

    /**
     * Process result with returned code and output
     *
     * @param array $result The result of command with output and returnCode
     *
     * @throws DockerInstallationMissingException When returned code is 127
     * @throws ComposeFileNotFoundException When no compose file precise and docker-compose.yml not found
     * @throws DockerHostConnexionErrorException When we can't connect to docker host
     * @throws \Exception When an unknown error is returned
     */
    private function processResult($result)
    {
        if ($result['code'] === 127) {
            throw new DockerInstallationMissingException();
        }

        if ($result['code'] === 1) {
            if (!strpos($result['output'], 'DOCKER_HOST')) {
                if (!strpos($result['output'], 'docker-compose.yml')) {
                    throw new Exception($result['output']);
                } else {
                    throw new ComposeFileNotFoundException();
                }
            } else {
                throw new DockerHostConnexionErrorException();
            }
        }

        return $result['output'];
    }

    /**
     * Create the composeFileCollection from the type of value given
     *
     * @param mixed $composeFiles The docker-compose files (can be array, string or ComposeFile)
     *
     * @return ComposeFileCollection
     */
    private function createComposeFileCollection($composeFiles)
    {
        if (!$composeFiles instanceof ComposeFileCollection) {
            if (!is_array($composeFiles)) {
                return new ComposeFileCollection([$composeFiles]);
            } else {
                return new ComposeFileCollection($composeFiles);
            }
        } else {
            return $composeFiles;
        }
    }

    /**
     * Format the command to execute
     *
     * @param string                $subcommand   The subcommand to pass to docker-compose command
     * @param ComposeFileCollection $composeFiles The compose files to precise in the command
     */
    private function formatCommand($subcommand, ComposeFileCollection $composeFiles)
    {
        $command = new Command("docker-compose");

        # Set working directory
        if (!empty($this->cwd)) {
            $command->procCwd = $this->cwd;
        }

        # Set environment variables
        if (!empty($this->env)) {
            $command->procEnv = $this->env;
        }

        # Add files names
        foreach ($composeFiles->getAll() as $composeFile) {
            $command->addArg('-f', $composeFile->getFileName());
        }

        # Add project name
        if ($composeFiles->getProjectName() != null) {
            $command->addArg('--project-name', $composeFiles->getProjectName());
        }

        $command->addArg($subcommand);

        return $command;
    }

    /**
     * Execute docker-compose commande
     * @codeCoverageIgnore
     * @param Command $command The command to execute.
     */
    protected function execute($command)
    {
        if ($command->execute()) {
            $output = $command->getOutput();
        } else {
            $output = $command->getError();
        }

        return array(
            'output' => $output,
            'code' => $command->getExitCode()
        );
    }
}
