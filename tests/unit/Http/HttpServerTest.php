<?php
namespace Ratchet\Http;

use Ratchet\AbstractMessageComponentTestCase;
use Ratchet\ConnectionInterface;

/**
 * @covers Ratchet\Http\HttpServer
 */
class HttpServerTest extends AbstractMessageComponentTestCase {
    public function setUp(): void {
        parent::setUp();
        $this->_conn->httpHeadersReceived = true;
    }

    public function getConnectionClassString() {
        return ConnectionInterface::class;
    }

    public function getDecoratorClassString() {
        return HttpServer::class;
    }

    public function getComponentClassString() {
        return HttpServerInterface::class;
    }

    public function testOpen() {
        $headers = "GET / HTTP/1.1\r\nHost: socketo.me\r\n\r\n";

        $this->_conn->httpHeadersReceived = false;
        $this->_app->expects($this->once())->method('onOpen')->with($this->isExpectedConnection());
        $this->_serv->onMessage($this->_conn, $headers);
    }

    public function testOnMessageAfterHeaders() {
        $headers = "GET / HTTP/1.1\r\nHost: socketo.me\r\n\r\n";
        $this->_conn->httpHeadersReceived = false;
        $this->_serv->onMessage($this->_conn, $headers);

        $message = "Hello World!";
        $this->_app->expects($this->once())->method('onMessage')->with($this->isExpectedConnection(), $message);
        $this->_serv->onMessage($this->_conn, $message);
    }

    public function testBufferOverflow() {
        $this->_conn->expects($this->once())->method('close');
        $this->_conn->httpHeadersReceived = false;

        $this->_serv->onMessage($this->_conn, str_repeat('a', 5000));
    }

    public function testCloseIfNotEstablished() {
        $this->_conn->httpHeadersReceived = false;
        $this->_conn->expects($this->once())->method('close');
        $this->_serv->onError($this->_conn, new \Exception('Whoops!'));
    }

    public function testBufferHeaders() {
        $this->_conn->httpHeadersReceived = false;
        $this->_app->expects($this->never())->method('onOpen');
        $this->_app->expects($this->never())->method('onMessage');

        $this->_serv->onMessage($this->_conn, "GET / HTTP/1.1");
    }
}
