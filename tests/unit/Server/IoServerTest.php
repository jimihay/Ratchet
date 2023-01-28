<?php
namespace Ratchet\Server;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;
use React\Socket\ConnectionInterface as SocketConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\LoopInterface;
use React\Socket\Server;

/**
 * @covers Ratchet\Server\IoServer
 */
class IoServerTest extends TestCase {
    protected $server;

    protected $app;

    protected $port;

    protected $reactor;

    protected function tickLoop(LoopInterface $loop) {
        $loop->futureTick(function () use ($loop) {
            $loop->stop();
        });

        $loop->run();
    }

    public function setUp(): void {
        $this->app = $this->createMock(MessageComponentInterface::class);

        $loop = new StreamSelectLoop;
        $this->reactor = new Server(0, $loop);

        $uri = $this->reactor->getAddress();
        $this->port   = parse_url((strpos($uri, '://') === false ? 'tcp://' : '') . $uri, PHP_URL_PORT);
        $this->server = new IoServer($this->app, $this->reactor, $loop);
    }

    public function testOnOpen() {
        $this->app->expects($this->once())->method('onOpen')->with($this->isInstanceOf(ConnectionInterface::class));

        $client = stream_socket_client("tcp://localhost:{$this->port}");

        $this->tickLoop($this->server->loop);

        //$this->assertTrue(is_string($this->app->last['onOpen'][0]->remoteAddress));
        //$this->assertTrue(is_int($this->app->last['onOpen'][0]->resourceId));
    }

    public function testOnData() {
        $msg = 'Hello World!';

        $this->app->expects($this->once())->method('onMessage')->with(
            $this->isInstanceOf(ConnectionInterface::class)
          , $msg
        );

        $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($client, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($client, SOL_SOCKET, SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        $this->tickLoop($this->server->loop);

        socket_write($client, $msg);
        $this->tickLoop($this->server->loop);

        socket_shutdown($client, 1);
        socket_shutdown($client, 0);
        socket_close($client);

        $this->tickLoop($this->server->loop);
    }

    public function testOnClose() {
        $this->app->expects($this->once())->method('onClose')->with($this->isInstanceOf(ConnectionInterface::class));

        $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($client, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($client, SOL_SOCKET, SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        $this->tickLoop($this->server->loop);

        socket_shutdown($client, 1);
        socket_shutdown($client, 0);
        socket_close($client);

        $this->tickLoop($this->server->loop);
    }

    public function testFactory() {
        $this->assertInstanceOf(IoServer::class, IoServer::factory($this->app, 0));
    }

    public function testNoLoopProvidedError() {
        $this->expectException(\RuntimeException::class);

        $io   = new IoServer($this->app, $this->reactor);
        $io->run();
    }

    public function testOnErrorPassesException() {
        $conn = $this->createMock(SocketConnectionInterface::class);
        $conn->decor = $this->createMock(ConnectionInterface::class);
        $err  = new \Exception("Nope");

        $this->app->expects($this->once())->method('onError')->with($conn->decor, $err);

        $this->server->handleError($err, $conn);
    }

    public function onErrorCalledWhenExceptionThrown() {
        $this->markTestIncomplete("Need to learn how to throw an exception from a mock");

        $conn = $this->createMock(SocketConnectionInterface::class);
        $this->server->handleConnect($conn);

        $e = new \Exception;
        $this->app->expects($this->once())->method('onMessage')->with($this->isInstanceOf(ConnectionInterface::class), 'f')->will($e);
        $this->app->expects($this->once())->method('onError')->with($this->isInstanceOf(ConnectionInterface::class, $e));

        $this->server->handleData('f', $conn);
    }
}
