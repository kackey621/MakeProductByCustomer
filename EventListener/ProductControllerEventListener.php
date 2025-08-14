<?php

namespace Plugin\MPBC43\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Eccube\Entity\Product;
use Psr\Log\LoggerInterface;

class ProductControllerEventListener implements EventSubscriberInterface
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger = null
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * 商品編集画面及びフロント商品詳細画面でMPBCプラグインで作成された削除済み商品へのアクセスを処理
     */
    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        // ProductControllerの編集アクションかチェック
        if (!is_array($controller) || !isset($controller[0])) {
            return;
        }

        $controllerClass = get_class($controller[0]);
        $action = $controller[1] ?? '';

        // 管理画面の商品編集またはフロントの商品詳細の場合
        if (($controllerClass === 'Eccube\Controller\Admin\Product\ProductController' && $action === 'edit') ||
            ($controllerClass === 'Eccube\Controller\ProductController' && $action === 'detail')) {
            
            $productId = $request->get('id');
            
            if ($productId) {
                try {
                    $product = $this->entityManager->getRepository(Product::class)->find($productId);
                    
                    if (!$product) {
                        // 商品が存在しない場合の処理
                        if ($this->logger) {
                            $this->logger->warning('MPBC: Attempted to access deleted product', [
                                'product_id' => $productId,
                                'controller' => $controllerClass,
                                'action' => $action
                            ]);
                        }
                        
                        // 管理画面の場合は商品一覧にリダイレクト
                        if ($controllerClass === 'Eccube\Controller\Admin\Product\ProductController') {
                            $response = new RedirectResponse(
                                $this->urlGenerator->generate('admin_product', [], UrlGeneratorInterface::ABSOLUTE_URL)
                            );
                        } else {
                            // フロントの場合は404エラーページにリダイレクト
                            $response = new RedirectResponse(
                                $this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)
                            );
                        }
                        
                        $event->setController(function() use ($response) {
                            return $response;
                        });
                    }
                } catch (\Exception $e) {
                    // エラーが発生した場合の処理
                    if ($this->logger) {
                        $this->logger->error('MPBC: Error accessing product', [
                            'product_id' => $productId,
                            'controller' => $controllerClass,
                            'action' => $action,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    // 管理画面の場合は商品一覧にリダイレクト
                    if ($controllerClass === 'Eccube\Controller\Admin\Product\ProductController') {
                        $response = new RedirectResponse(
                            $this->urlGenerator->generate('admin_product', [], UrlGeneratorInterface::ABSOLUTE_URL)
                        );
                    } else {
                        // フロントの場合はホームページにリダイレクト
                        $response = new RedirectResponse(
                            $this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)
                        );
                    }
                    
                    $event->setController(function() use ($response) {
                        return $response;
                    });
                }
            }
        }
    }

    /**
     * NoResultExceptionやNotFoundHttpExceptionが発生した場合の処理
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // NoResultExceptionまたはNotFoundHttpExceptionの場合
        if ($exception instanceof NoResultException || 
            $exception instanceof NotFoundHttpException) {
            
            $route = $request->attributes->get('_route');
            
            // 商品編集関連のルートの場合（管理画面）
            if ($route === 'admin_product_product_edit' || 
                strpos($route, 'admin_product') !== false ||
                strpos($request->getRequestUri(), '/admin/product/product/') !== false) {
                
                if ($this->logger) {
                    $this->logger->warning('MPBC: Exception caught for admin product operation', [
                        'route' => $route,
                        'uri' => $request->getRequestUri(),
                        'exception' => $exception->getMessage()
                    ]);
                }

                // 商品一覧にリダイレクト
                $response = new RedirectResponse(
                    $this->urlGenerator->generate('admin_product', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );
                $event->setResponse($response);
            }
            // フロント商品詳細関連のルートの場合
            elseif ($route === 'product_detail' || 
                    strpos($request->getRequestUri(), '/products/detail/') !== false) {
                
                if ($this->logger) {
                    $this->logger->warning('MPBC: Exception caught for frontend product operation', [
                        'route' => $route,
                        'uri' => $request->getRequestUri(),
                        'exception' => $exception->getMessage()
                    ]);
                }

                // ホームページにリダイレクト
                $response = new RedirectResponse(
                    $this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );
                $event->setResponse($response);
            }
        }
    }
}
