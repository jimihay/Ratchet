<?php
namespace Ratchet\Http;

use Psr\Http\Message\RequestInterface;
use Ratchet\AbstractMessageComponentTestCase;
use Ratchet\ConnectionInterface;

/**
 * @covers Ratchet\Http\OriginCheck
 */
class OriginCheckTest extends AbstractMessageComponentTestCase {
    protected $_reqStub;

    public function setUp(): void {
        $this->_reqStub = $this->createMock(RequestInterface::class);
        $this->_reqStub->expects($this->any())->method('getHeader')->will($this->returnValue(['localhost']));

        parent::setUp();

        $this->_serv->allowedOrigins[] = 'localhost';
    }

    protected function doOpen($conn) {
        $this->_serv->onOpen($conn, $this->_reqStub);
    }

    public function getConnectionClassString() {
        return ConnectionInterface::class;
    }

    public function getDecoratorClassString() {
        return OriginCheck::class;
    }

    public function getComponentClassString() {
        return HttpServerInterface::class;
    }

    public function testCloseOnNonMatchingOrigin() {
        $this->_serv->allowedOrigins = ['socketo.me'];
        $this->_conn->expects($this->once())->method('close');

        $this->_serv->onOpen($this->_conn, $this->_reqStub);
    }

    public function testOnMessage() {
        $this->passthroughMessageTest('Hello World!');
    }
}
