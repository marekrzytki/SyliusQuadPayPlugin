<?php

/*
 * This file was created by developers working at BitBag
 * Do you need more information about us and what we do? Visit our https://bitbag.io website!
 * We are hiring developers from all over the world. Join us and start your new, exciting adventure and become part of us: https://bitbag.io/career
*/

declare(strict_types=1);

namespace BitBag\SyliusQuadPayPlugin\Action;

use BitBag\SyliusQuadPayPlugin\Action\Api\ApiAwareTrait;
use BitBag\SyliusQuadPayPlugin\Client\QuadPayApiClientInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Security\TokenInterface;

final class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /**
     * @inheritdoc
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        if (true === isset($details['orderToken'])) {
            return;
        }

        /** @var TokenInterface $token */
        $token = $request->getToken();

        $details['merchant'] = [
            'redirectConfirmUrl' => $token->getTargetUrl(),
            'redirectCancelUrl' => $token->getTargetUrl() . '?&' . http_build_query(['status' => QuadPayApiClientInterface::STATUS_ABANDONED]),
        ];

        $order = $this->quadPayApiClient->createOrder($details->getArrayCopy());

        $details['orderToken'] = $order['token'];
        $details['orderStatus'] = QuadPayApiClientInterface::STATUS_CREATED;

        if (isset($order['orderId'])) {
            $details['orderId'] = $order['orderId'];
        }

        throw new HttpRedirect($order['redirectUrl']);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
