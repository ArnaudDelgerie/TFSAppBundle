<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class HealthzListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/healthz' || !\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $event->setResponse(new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain']));
        $event->stopPropagation();
    }
}
