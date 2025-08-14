<?php

namespace Plugin\MPBC43\EventListener;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 商品一覧からカスタム商品を除外するEventListener
 */
class ProductListEventListener implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_PRODUCT_INDEX_SEARCH => 'onProductIndexSearch',
        ];
    }

    /**
     * 商品一覧検索時にカスタム商品を除外
     *
     * @param EventArgs $event
     */
    public function onProductIndexSearch(EventArgs $event)
    {
        $searchData = $event->getArgument('searchData');
        $qb = $event->getArgument('qb');
        
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }
        
        $sessionId = $request->getSession()->getId();
        
        // カスタム商品（セッション情報を含む商品）を除外
        $qb->andWhere('p.description_detail NOT LIKE :session_filter')
           ->setParameter('session_filter', '%セッション: ' . substr($sessionId, 0, 8) . '%');
    }
}
