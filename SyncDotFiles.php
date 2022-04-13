<?php

class SyncDotFiles
{
    public const REGISTRY_FILE = 'registry.json';
    public const FILES_ROOT_DIRECTORY = 'files';
    public const COMMANDS_HISTORY_FILE = 'commands-history.txt';

    // Préfixe ajouter à toutes les commandes passan par self::prefixedRun
    private string $prefixCommand;
    // Chemin vers le repertoire home du user
    private string $homeDirectory;

    // Contient la liste des fichiers suivis
    protected array $registry;

    // Output de toutes les commandes executées
    protected array $output;

    public function __construct(
        protected string $repoDir,
        protected string $repoRemoteUrl,
    )
    {
        $this->prefixCommand = 'cd ' . $this->repoDir . ' && ';

        if ($this->isInit()) {
            $this->registry = $this->getRegistry();
        }

        $this->homeDirectory = $this->run('echo ~')[0];
    }

    protected function getRepoDirFilePath(string $path): string
    {
        return $this->repoDir . '/' . $path;
    }

    protected function getRegistry(): array
    {
        return json_decode(file_get_contents($this->getRepoDirFilePath(self::REGISTRY_FILE)), true);
    }

    protected function writeRegistry(array $registry): int|bool
    {
        return file_put_contents($this->getRepoDirFilePath(self::REGISTRY_FILE), json_encode($registry, JSON_THROW_ON_ERROR));
    }

    protected function replaceParams(string $str, array $params = []): string
    {
        foreach ($params as $key => $value) {
            $str = str_replace('{' . $key . '}', $value, $str);
        }

        return $str;
    }

    protected function writeLn(string $message, array $params = [])
    {
        $message = $this->replaceParams($message, $params);

        echo "$message\n";
    }

    protected function run(string $command, array $params = []): array
    {
        $command = $this->replaceParams($command, $params);

        if (is_file($this->getRepoDirFilePath(self::COMMANDS_HISTORY_FILE))) {
            file_put_contents($this->getRepoDirFilePath(self::COMMANDS_HISTORY_FILE), $command . "\n", FILE_APPEND);
        }

        ob_start();
        exec($command, $output, $resultCode);
        $this->output[] = ob_get_clean();

        return $output;
    }

    protected function commit($message, $push = false)
    {
        $this->prefixedRun('git add .');
        $this->prefixedRun('git commit -m "{message}"', ['message' => addslashes($message)]);

        if ($push) {
            $this->prefixedRun('git push');
        }
    }

    protected function prefixedRun(string $command, array $params = [])
    {
        return $this->run($this->prefixCommand . $command, $params);
    }

    protected function isGitRepository(): bool
    {
        return is_dir($this->getRepoDirFilePath('.git'));
    }

    protected function initRemoteRepository()
    {
        $this->prefixedRun('git init');
        $this->prefixedRun('git remote add origin {url}', ['url' => $this->repoRemoteUrl]);
        $this->prefixedRun('git switch -c main');

        if (!is_file($this->getRepoDirFilePath(self::REGISTRY_FILE))) {
            // Repo is empty => prepare it

            file_put_contents($this->getRepoDirFilePath('.gitignore'), implode("\n", [
                self::COMMANDS_HISTORY_FILE,
            ]));

            $this->writeRegistry([]);

            $this->prefixedRun('mkdir -p {path}', ['path' => $this->getRepoDirFilePath(self::FILES_ROOT_DIRECTORY)]);
            $this->prefixedRun('touch {path}', ['path' => $this->getRepoDirFilePath(self::FILES_ROOT_DIRECTORY . '/.git_keep')]);

            $this->commit('init');
            $this->prefixedRun('git push --set-upstream origin main');
        }
    }

    protected function initLocalRepository()
    {
        $this->prefixedRun('git init');
        $this->prefixedRun('git remote add origin {url}', ['url' => $this->repoRemoteUrl]);
        $this->prefixedRun('git pull origin main');
        $this->prefixedRun('git branch -u origin/main main');
    }

    protected function areFilesSames(string $path1, string $path2): bool
    {
        if (!is_file($path1) || !is_file($path2)) {
            return false;
        }

        return file_get_contents($path1) === file_get_contents($path2);
    }

    protected function getRealPathFromRegistryFilePath(string $registryFilePath): string
    {
        return $this->getRepoDirFilePath(self::FILES_ROOT_DIRECTORY) . (str_starts_with($registryFilePath, '~') ? '/' : '') . $registryFilePath;
    }

    protected function copyFromRepoToFiles()
    {
        foreach ($this->getRegistry() as $currFile) {
            $fromPath = $this->getRealPathFromRegistryFilePath($currFile);

            if ($this->areFilesSames(str_replace('~', $this->homeDirectory, $currFile), $fromPath) === false) {
                $this->writeLn('Copying repository {file} to {to}..', ['file' => $currFile, 'to' => $currFile]);
                $this->prefixedRun('cp {from} {to}', ['from' => $fromPath, 'to' => $currFile]);
            }
        }
    }

    protected function copyFromFilesToRepo()
    {
        foreach ($this->getRegistry() as $currFile) {

            $this->prefixedRun('mkdir -p "{path}"', ['path' => $this->getRepoDirFilePath(self::FILES_ROOT_DIRECTORY) . '/' . dirname($currFile)]);
            $to = $this->getRepoDirFilePath(self::FILES_ROOT_DIRECTORY) . '/' . $currFile;

            if ($this->areFilesSames(str_replace('~', $this->homeDirectory, $currFile), $to) === false) {
                $this->writeLn('Copying existing {file} to repository..', ['file' => $currFile]);
                $this->prefixedRun('cp {from} {to}', ['from' => $currFile, 'to' => $to]);
            }
        }
    }

    protected function isInit(): bool
    {
        return is_dir($this->repoDir) && $this->isGitRepository();
    }

    public function initLocal(): void
    {
        if (!is_dir($this->repoDir)) {
            $this->run('mkdir -p {path}', ['path' => $this->repoDir]);
        }

        if (!$this->isGitRepository()) {
            $this->initLocalRepository();
        }
    }

    public function initRemote(): void
    {
        if (!is_dir($this->repoDir)) {
            $this->run('mkdir -p {path}', ['path' => $this->repoDir]);
        }

        if (!$this->isGitRepository()) {
            $this->initRemoteRepository();
        }
    }

    public function pullCopy(): void
    {
        if (!$this->isInit()) {
            die($this->writeLn('not init'));
        }

        $this->prefixedRun('git pull');

        $this->copyFromRepoToFiles();
    }

    public function copyPush(): void
    {
        if (!$this->isInit()) {
            die($this->writeLn('not init'));
        }

        $this->copyFromFilesToRepo();

        // First we commit existing files
        $this->commit('update before pull');

        // Then pull trying rebase => can failed if merge needed
        $this->prefixedRun('git pull --rebase');

        // PUSH
        $this->prefixedRun('git push');
    }

    public function add(string $filePath): bool
    {
        if (!$this->isInit()) {
            die($this->writeLn('not init'));
        }

        if (!is_file($filePath)) {
            die("File does not exists\n");
        }

        $homeFilePath = str_replace($this->homeDirectory, '~', $filePath);

        if (!in_array($homeFilePath, $this->registry)) {
            $this->registry[] = $homeFilePath;

            $this->writeRegistry($this->registry);

//            $this->commit('Added file: ' . $filePath);
            $this->commit('Added file: ' . $filePath, true);

            return true;
        }

        $this->writeLn('File already sync');

        return false;
    }

    public function remove(string $filePath): bool
    {
        if (!$this->isInit()) {
            die($this->writeLn('not init'));
        }

        $homeFilePath = str_replace($this->homeDirectory, '~', $filePath);

        if (in_array($homeFilePath, $this->registry)) {
            array_splice($this->registry, array_search($homeFilePath, $this->registry));

            $this->writeRegistry($this->registry);

            $this->prefixedRun('git rm {path}', ['path' => $this->getRealPathFromRegistryFilePath($homeFilePath)]);
            $this->commit('Removed file: ' . $filePath, true);

            return true;
        }

        $this->writeLn('File not sync');

        return false;
    }

    public function getOutput(): array
    {
        return array_filter($this->output);
    }
}