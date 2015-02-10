<?php

namespace Pierstoval\Bundle\ApiBundle\Services;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OriginChecker {

    /**
     * @var array
     */
    private $allowedOrigins;

    /**
     * @var string
     */
    private $kernelEnvironment;

    public function __construct(array $allowedOrigins, $kernelEnvironment)
    {
        $this->allowedOrigins = $allowedOrigins;
        $this->kernelEnvironment = $kernelEnvironment;
    }

    /**
     * @param Request $request
     * @throws AccessDeniedException
     */
    public function checkRequest(Request $request)
    {
        $allowedOrigins = $this->allowedOrigins;

        // Allows automatically the current server to allow internal requests
        $allowedOrigins[] = $request->server->get('SERVER_ADDR');
        $host = $request->getHost();
        if (!in_array($host, $allowedOrigins)) {
            $allowedOrigins[] = $host;
        }

        $match = false;

        // Checks Origin header
        if ($request->headers->has('Origin')) {
            $origin = $request->headers->get('Origin');
            $origin = preg_replace('~https?://~isUu', '', $origin);
            $origin = trim($origin, '/');
            // Checks if the header corresponds to an allowed origin
            foreach ($allowedOrigins as $address) {
                if ($origin === $address) {
                    $match = true;
                }
            }
        }

        // Also checks the users' IP address as a potential allowed origin
        $remoteAddr = $request->server->get('REMOTE_ADDR');
        foreach ($allowedOrigins as $address) {
            if ($remoteAddr === $address) {
                $match = true;
            }
        }

        if ($match === false) {
            throw new AccessDeniedException('Origin not allowed.');
        }

    }

}
