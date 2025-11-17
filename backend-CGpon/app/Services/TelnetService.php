<?php

namespace App\Services;

use Graze\TelnetClient\TelnetClient;
use Graze\TelnetClient\TelnetClientInterface;
use Graze\TelnetClient\Exception\TelnetException;

class TelnetService
{
    protected TelnetClientInterface $client;
    protected string $host;
    protected int $port;
    protected string $prompt;
    protected string $errorPrompt;
    protected string $lineEnding;

    public function __construct(
        string $host = '172.20.0.20',
        int $port = 23,
        string $prompt = '$',
        string $errorPrompt = 'ERROR',
        string $lineEnding = "\n"
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->prompt = $prompt;
        $this->errorPrompt = $errorPrompt;
        $this->lineEnding = $lineEnding;
        $this->client = TelnetClient::factory();
    }

    public function connect()
    {
        try {
            $this->client->connect(
                "{$this->host}:{$this->port}",
                $this->prompt,
                $this->errorPrompt,
                $this->lineEnding
            );
            echo "Conexión establecida con {$this->host}:{$this->port}\n";
        } catch (TelnetException $e) {
            // Handle connection error
            throw new \RuntimeException("Unable to connect to Telnet server: " . $e->getMessage());
        }
    }

    public function login(string $username, string $password)
    {
        echo "Enviando nombre de usuario...\n";
        // return $this->readResponse();
        $res=$this->executeCommand($username, 'Password:');
        return $res;
        // return $this->readResponse();
        echo "Enviando contraseña...\n";
        $this->executeCommand($password);
        // return $this->readResponse();
    }

    public function executeCommand(string $command, string $expectedPrompt = null): string
    {
        try {
            echo "Ejecutando comando: $command\n";
            $response = $this->client->execute($command, $expectedPrompt ?? $this->prompt);
            // $response = $this->client->execute($command, $expectedPrompt);
            return "Respuesta recibida:\n" . $response->getResponseText() . "\n";

            if ($response->isError()) {
                throw new \RuntimeException("Telnet command error: " . $response->getResponseText());
            }
            echo "Respuesta recibida:\n" . $response->getResponseText() . "\n";
            return $response->getResponseText();
        } catch (TelnetException $e) {
            // Handle execution error
            throw new \RuntimeException("Telnet command execution failed: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
        echo "Conexión cerrada.\n";
    }


    public function readResponse(): string
    {
        try {
            $response = $this->client->getResponse();
            echo "Respuesta del servidor:\n" . $response->getResponseText() . "\n";
            return $response->getResponseText();
        } catch (TelnetException $e) {
            throw new \RuntimeException("Error al leer la respuesta del servidor: " . $e->getMessage());
        }
    }
}
