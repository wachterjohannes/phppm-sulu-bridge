<?php

namespace Sulu\Component\PHPPM;

use PHPPM\Bridges\BridgeInterface;

/**
 * Implements a bridge to bootstrap sulu application in php-pm
 */
class SuluBridge implements BridgeInterface
{
    /**
     * {@inheritdoc}
     */
    public function bootstrap($appBootstrap, $appenv)
    {
        // TODO: Implement bootstrap() method.
    }

    /**
     * {@inheritdoc}
     */
    public function onRequest(\React\Http\Request $request, \React\Http\Response $response)
    {
        // TODO: Implement onRequest() method.
    }
}
