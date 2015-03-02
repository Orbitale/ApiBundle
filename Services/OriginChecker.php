<?php

namespace Pierstoval\Bundle\ApiBundle\Services;


use Symfony\Component\HttpFoundation\HeaderBag;
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

        if (
            $this->checkOriginHeader($request->headers)
            || $this->checkRemoteIp($request->server->get('REMOTE_ADDR'))
        ) {
            $match = true;
        }

        if ($match === false) {
            throw new AccessDeniedException('Origin not allowed.');
        }

    }

    /**
     * Check Origin header to see if origin is in the `allowed_origins` parameter
     * @param HeaderBag $headers
     * @return bool
     */
    public function checkOriginHeader(HeaderBag $headers)
    {
        if ($headers->has('Origin')) {
            $origin = $headers->get('Origin');
            $origin = preg_replace('~https?://~isUu', '', $origin);
            $origin = trim($origin, '/');
            // Checks if the header corresponds to an allowed origin
            foreach ($this->allowedOrigins as $address) {
                if ($origin === $address) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Also checks the users' IP address as a potential allowed origin
     * @param string $remoteAddr
     * @return bool
     */
    private function checkRemoteIp($remoteAddr)
    {
        foreach ($this->allowedOrigins as $address) {
            if ($remoteAddr === $address) {
                return true;
            }
        }
        return false;
    }

}
