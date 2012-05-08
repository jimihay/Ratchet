<?php
namespace Ratchet\Component\WAMP;
use Ratchet\Component\WebSocket\WebSocketComponentInterface;
use Ratchet\Resource\ConnectionInterface;

/**
 * WebSocket Application Messaging Protocol
 * 
 * @link http://wamp.ws/spec
 * @link https://github.com/oberstet/AutobahnJS
 *
 * +--------------+----+------------------+
 * | Message Type | ID | DIRECTION        |
 * |--------------+----+------------------+
 * | WELCOME      | 0  | Server-to-Client |
 * | PREFIX       | 1  | Bi-Directional   |
 * | CALL         | 2  | Client-to-Server |
 * | CALL RESULT  | 3  | Server-to-Client |
 * | CALL ERROR   | 4  | Server-to-Client |
 * | SUBSCRIBE    | 5  | Client-to-Server |
 * | UNSUBSCRIBE  | 6  | Client-to-Server |
 * | PUBLISH      | 7  | Client-to-Server |
 * | EVENT        | 8  | Server-to-Client |
 * +--------------+----+------------------+
 */
class WAMPServerComponent implements WebSocketComponentInterface {
    const MSG_WELCOME     = 0;
    const MSG_PREFIX      = 1;
    const MSG_CALL        = 2;
    const MSG_CALL_RESULT = 3;
    const MSG_CALL_ERROR  = 4;
    const MSG_SUBSCRIBE   = 5;
    const MSG_UNSUBSCRIBE = 6;
    const MSG_PUBLISH     = 7;
    const MSG_EVENT       = 8;

    /**
     * @var WAMPServerComponentInterface
     */
    protected $_decorating;

    /**
     * @var SplObjectStorage
     */
    protected $connections;

    /**
     * @param WAMPServerComponentInterface An class to propagate calls through
     */
    public function __construct(WAMPServerComponentInterface $server_component) {
        $this->_decorating = $server_component;
        $this->connections = new \SplObjectStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocol() {
        return 'wamp';
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn) {
        $decor = new WampConnection($conn);
        $this->connections->attach($conn, $decor);

        $this->_decorating->onOpen($decor);
    }

    /**
     * @{inheritdoc}
     * @throws Exception
     * @throws JSONException
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $from = $this->connections[$from];

        if (null === ($json = @json_decode($msg, true))) {
            throw new JSONException;
        }

        switch ($json[0]) {
            case static::MSG_PREFIX:
                $from->WAMP->prefixes[$json[1]] = $json[2];
//                $from->WAMP->prefixes($json[1], $json[2]);
            break;

            case static::MSG_CALL:
                array_shift($json);
                $callID  = array_shift($json);
                $procURI = array_shift($json);

                if (count($json) == 1 && is_array($json[0])) {
                    $json = $json[0];
                }

                $this->_decorating->onCall($from, $callID, $procURI, $json);
            break;

            case static::MSG_SUBSCRIBE:
                $this->_decorating->onSubscribe($from, $from->getUri($json[1]));
            break;

            case static::MSG_UNSUBSCRIBE:
                $this->_decorating->onUnSubscribe($from, $from->getUri($json[1]));
            break;

            case static::MSG_PUBLISH:
                $this->_decorating->onPublish($from, $from->getUri($json[1]), $json[2]);
            break;

            default:
                throw new Exception('Invalid message type');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        $decor = $this->connections[$conn];
        $this->connections->detach($conn);

        $this->_decorating->onClose($decor);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        return $this->_decorating->onError($this->connections[$conn], $e);
    }
}