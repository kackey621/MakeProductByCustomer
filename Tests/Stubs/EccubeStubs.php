<?php

/**
 * EC-CUBEフレームワーク クラススタブ
 *
 * CI環境でEC-CUBEがインストールされていない場合にユニットテストを実行するための
 * 最小限のスタブクラス定義。
 */

// ---------------------------------------------------------------------------
// PSR\Log
// ---------------------------------------------------------------------------
namespace Psr\Log {
    interface LoggerInterface
    {
        public function emergency($message, array $context = []);
        public function alert($message, array $context = []);
        public function critical($message, array $context = []);
        public function error($message, array $context = []);
        public function warning($message, array $context = []);
        public function notice($message, array $context = []);
        public function info($message, array $context = []);
        public function debug($message, array $context = []);
        public function log($level, $message, array $context = []);
    }
}

// ---------------------------------------------------------------------------
// Symfony EventDispatcher
// ---------------------------------------------------------------------------
namespace Symfony\Component\EventDispatcher {
    interface EventSubscriberInterface
    {
        public static function getSubscribedEvents();
    }
}

// ---------------------------------------------------------------------------
// Symfony HttpFoundation
// ---------------------------------------------------------------------------
namespace Symfony\Component\HttpFoundation\Session {
    interface SessionInterface
    {
        public function getId(): string;
        public function setId(string $id): void;
        public function getName(): string;
        public function setName(string $name): void;
        public function start(): bool;
        public function save(): void;
        public function has(string $name): bool;
        public function get(string $name, mixed $default = null): mixed;
        public function set(string $name, mixed $value): void;
        public function all(): array;
        public function replace(array $attributes): void;
        public function remove(string $name): mixed;
        public function clear(): void;
        public function isStarted(): bool;
    }
}

namespace Symfony\Component\HttpFoundation {
    class Request
    {
        public $request;

        public function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface
        {
            return new class implements \Symfony\Component\HttpFoundation\Session\SessionInterface {
                public function getId(): string { return 'test_session_id'; }
                public function setId(string $id): void {}
                public function getName(): string { return 'PHPSESSID'; }
                public function setName(string $name): void {}
                public function start(): bool { return true; }
                public function save(): void {}
                public function has(string $name): bool { return false; }
                public function get(string $name, mixed $default = null): mixed { return $default; }
                public function set(string $name, mixed $value): void {}
                public function all(): array { return []; }
                public function replace(array $attributes): void {}
                public function remove(string $name): mixed { return null; }
                public function clear(): void {}
                public function isStarted(): bool { return true; }
            };
        }
    }

    class RequestStack
    {
        public function getCurrentRequest(): ?Request
        {
            return null;
        }
    }
}

// ---------------------------------------------------------------------------
// Doctrine DBAL
// ---------------------------------------------------------------------------
namespace Doctrine\DBAL {
    interface Connection
    {
        public function beginTransaction(): bool;
        public function commit(): bool;
        public function rollBack(): bool;
        public function isTransactionActive(): bool;
    }
}

// ---------------------------------------------------------------------------
// Doctrine Persistence
// ---------------------------------------------------------------------------
namespace Doctrine\Persistence {
    interface ManagerRegistry
    {
        public function getManagerForClass($class);
        public function getManager($name = null);
    }
}

// ---------------------------------------------------------------------------
// Doctrine ORM
// ---------------------------------------------------------------------------
namespace Doctrine\ORM {
    interface EntityManagerInterface
    {
        public function find($className, $id);
        public function persist($object): void;
        public function flush(): void;
        public function refresh($object): void;
        public function remove($object): void;
        public function getConnection(): \Doctrine\DBAL\Connection;
        public function getRepository($className);
    }

    abstract class EntityRepository
    {
        public function __construct($registry = null, $entityClass = null)
        {
            // Stub constructor - no-op for testing
        }

        public function find($id, $lockMode = null, $lockVersion = null) { return null; }
        public function findAll() { return []; }
        public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null) { return []; }
        public function findOneBy(array $criteria, ?array $orderBy = null) { return null; }
    }
}

// ---------------------------------------------------------------------------
// Doctrine Common Collections
// ---------------------------------------------------------------------------
namespace Doctrine\Common\Collections {
    class ArrayCollection implements \Countable, \IteratorAggregate
    {
        private array $elements;

        public function __construct(array $elements = [])
        {
            $this->elements = $elements;
        }

        public function add($element): bool
        {
            $this->elements[] = $element;
            return true;
        }

        public function removeElement($element): bool
        {
            $key = array_search($element, $this->elements, true);
            if ($key === false) {
                return false;
            }
            unset($this->elements[$key]);
            $this->elements = array_values($this->elements);
            return true;
        }

        public function count(): int
        {
            return count($this->elements);
        }

        public function getIterator(): \ArrayIterator
        {
            return new \ArrayIterator($this->elements);
        }

        public function toArray(): array
        {
            return $this->elements;
        }

        public function isEmpty(): bool
        {
            return empty($this->elements);
        }
    }
}

// ---------------------------------------------------------------------------
// EC-CUBE Event系
// ---------------------------------------------------------------------------
namespace Eccube\Event {
    class EventArgs
    {
        private array $args;

        public function __construct(array $args = [])
        {
            $this->args = $args;
        }

        public function getArgument(string $key)
        {
            return $this->args[$key] ?? null;
        }

        public function setArgument(string $key, $value): void
        {
            $this->args[$key] = $value;
        }
    }

    class EccubeEvents
    {
        const FRONT_SHOPPING_COMPLETE_INITIALIZE = 'eccube.event.front.shopping.complete.initialize';
        const FRONT_CART_INDEX_INITIALIZE = 'eccube.event.front.cart.index.initialize';
        const FRONT_CART_ADD_COMPLETE = 'eccube.event.front.cart.add.complete';
        const FRONT_PRODUCT_INDEX_SEARCH = 'eccube.event.front.product.index.search';
    }
}

// ---------------------------------------------------------------------------
// EC-CUBE Master entities
// ---------------------------------------------------------------------------
namespace Eccube\Entity\Master {
    class ProductStatus
    {
        const DISPLAY_SHOW = 1;
        const DISPLAY_HIDE = 2;

        private $id;
        private $name;

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }

        public function getName()
        {
            return $this->name;
        }

        public function setName($name): self
        {
            $this->name = $name;
            return $this;
        }
    }

    class SaleType
    {
        const SALE_TYPE_NORMAL = 1;

        private $id;

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }
    }

    class OrderStatus
    {
        const NEW = 1;
        const CANCEL = 3;
        const DELIVERED = 5;
        const PAID = 6;

        private $id;
        private $name;

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }

        public function getName()
        {
            return $this->name;
        }

        public function setName($name): self
        {
            $this->name = $name;
            return $this;
        }
    }
}

// ---------------------------------------------------------------------------
// EC-CUBE Core Entities
// ---------------------------------------------------------------------------
namespace Eccube\Entity {
    use Doctrine\Common\Collections\ArrayCollection;

    class Product
    {
        private $id;
        private $name;
        private $status;
        private $descriptionDetail;
        private $descriptionList;
        private $createDate;
        private $updateDate;
        private $productClasses;

        public function __construct()
        {
            $this->productClasses = new ArrayCollection();
        }

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }

        public function getName()
        {
            return $this->name;
        }

        public function setName($name): self
        {
            $this->name = $name;
            return $this;
        }

        public function getStatus()
        {
            return $this->status;
        }

        public function setStatus($status): self
        {
            $this->status = $status;
            return $this;
        }

        public function getDescriptionDetail()
        {
            return $this->descriptionDetail;
        }

        public function setDescriptionDetail($description): self
        {
            $this->descriptionDetail = $description;
            return $this;
        }

        public function getDescriptionList()
        {
            return $this->descriptionList;
        }

        public function setDescriptionList($description): self
        {
            $this->descriptionList = $description;
            return $this;
        }

        public function getCreateDate()
        {
            return $this->createDate;
        }

        public function setCreateDate($date): self
        {
            $this->createDate = $date;
            return $this;
        }

        public function getUpdateDate()
        {
            return $this->updateDate;
        }

        public function setUpdateDate($date): self
        {
            $this->updateDate = $date;
            return $this;
        }

        public function getProductClasses()
        {
            return $this->productClasses;
        }

        public function addProductClass($productClass): self
        {
            $this->productClasses->add($productClass);
            return $this;
        }
    }

    class ProductClass
    {
        private $id;
        private $product;
        private $price01;
        private $price02;
        private $visible = true;
        private $stockUnlimited = false;
        private $stock;
        private $saleType;
        private $classCategory1;
        private $classCategory2;
        private $code;
        private $deliveryFee;
        private $productStock;
        private $createDate;
        private $updateDate;

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }

        public function getProduct()
        {
            return $this->product;
        }

        public function setProduct($product): self
        {
            $this->product = $product;
            return $this;
        }

        public function getPrice01()
        {
            return $this->price01;
        }

        public function setPrice01($price): self
        {
            $this->price01 = $price;
            return $this;
        }

        public function getPrice02()
        {
            return $this->price02;
        }

        public function setPrice02($price): self
        {
            $this->price02 = $price;
            return $this;
        }

        public function isVisible()
        {
            return $this->visible;
        }

        public function setVisible($visible): self
        {
            $this->visible = $visible;
            return $this;
        }

        public function isStockUnlimited()
        {
            return $this->stockUnlimited;
        }

        public function setStockUnlimited($unlimited): self
        {
            $this->stockUnlimited = $unlimited;
            return $this;
        }

        public function getStock()
        {
            return $this->stock;
        }

        public function setStock($stock): self
        {
            $this->stock = $stock;
            return $this;
        }

        public function getSaleType()
        {
            return $this->saleType;
        }

        public function setSaleType($saleType): self
        {
            $this->saleType = $saleType;
            return $this;
        }

        public function getClassCategory1()
        {
            return $this->classCategory1;
        }

        public function setClassCategory1($category): self
        {
            $this->classCategory1 = $category;
            return $this;
        }

        public function getClassCategory2()
        {
            return $this->classCategory2;
        }

        public function setClassCategory2($category): self
        {
            $this->classCategory2 = $category;
            return $this;
        }

        public function getCode()
        {
            return $this->code;
        }

        public function setCode($code): self
        {
            $this->code = $code;
            return $this;
        }

        public function getDeliveryFee()
        {
            return $this->deliveryFee;
        }

        public function setDeliveryFee($fee): self
        {
            $this->deliveryFee = $fee;
            return $this;
        }

        public function getProductStock()
        {
            return $this->productStock;
        }

        public function setProductStock($productStock): self
        {
            $this->productStock = $productStock;
            return $this;
        }

        public function getCreateDate()
        {
            return $this->createDate;
        }

        public function setCreateDate($date): self
        {
            $this->createDate = $date;
            return $this;
        }

        public function getUpdateDate()
        {
            return $this->updateDate;
        }

        public function setUpdateDate($date): self
        {
            $this->updateDate = $date;
            return $this;
        }
    }

    class ProductStock
    {
        private $productClass;
        private $stock;
        private $createDate;
        private $updateDate;

        public function getProductClass()
        {
            return $this->productClass;
        }

        public function setProductClass($productClass): self
        {
            $this->productClass = $productClass;
            return $this;
        }

        public function getStock()
        {
            return $this->stock;
        }

        public function setStock($stock): self
        {
            $this->stock = $stock;
            return $this;
        }

        public function setCreateDate($date): self
        {
            $this->createDate = $date;
            return $this;
        }

        public function setUpdateDate($date): self
        {
            $this->updateDate = $date;
            return $this;
        }
    }

    class Cart
    {
        private $cartItems;

        public function __construct()
        {
            $this->cartItems = new \Doctrine\Common\Collections\ArrayCollection();
        }

        public function getCartItems()
        {
            return $this->cartItems;
        }

        public function addCartItem($item): self
        {
            $this->cartItems->add($item);
            return $this;
        }

        public function removeCartItem($item): self
        {
            $this->cartItems->removeElement($item);
            return $this;
        }
    }

    class CartItem
    {
        private $productClass;
        private $quantity;

        public function getProductClass()
        {
            return $this->productClass;
        }

        public function setProductClass($productClass): self
        {
            $this->productClass = $productClass;
            return $this;
        }

        public function getQuantity()
        {
            return $this->quantity;
        }

        public function setQuantity($quantity): self
        {
            $this->quantity = $quantity;
            return $this;
        }
    }

    class Order
    {
        private $id;
        private $orderItems;
        private $orderStatus;

        public function __construct()
        {
            $this->orderItems = new \Doctrine\Common\Collections\ArrayCollection();
        }

        public function getId()
        {
            return $this->id;
        }

        public function setId($id): self
        {
            $this->id = $id;
            return $this;
        }

        public function getOrderItems()
        {
            return $this->orderItems;
        }

        public function getOrderStatus()
        {
            return $this->orderStatus;
        }

        public function setOrderStatus($status): self
        {
            $this->orderStatus = $status;
            return $this;
        }
    }

    class OrderItem
    {
        private $product;
        private $productClass;
        private $productName;

        public function getProduct()
        {
            return $this->product;
        }

        public function setProduct($product): self
        {
            $this->product = $product;
            return $this;
        }

        public function getProductClass()
        {
            return $this->productClass;
        }

        public function setProductClass($productClass): self
        {
            $this->productClass = $productClass;
            return $this;
        }

        public function getProductName()
        {
            return $this->productName;
        }

        public function setProductName($name): self
        {
            $this->productName = $name;
            return $this;
        }
    }

    class Page
    {
        private $url;
        private $name;

        public function setUrl($url): self
        {
            $this->url = $url;
            return $this;
        }

        public function getUrl()
        {
            return $this->url;
        }

        public function setName($name): self
        {
            $this->name = $name;
            return $this;
        }

        public function getName()
        {
            return $this->name;
        }
    }

    class Layout
    {
        private $id;

        public function getId()
        {
            return $this->id;
        }
    }
}

// ---------------------------------------------------------------------------
// EC-CUBE Controller
// ---------------------------------------------------------------------------
namespace Eccube\Controller {
    abstract class AbstractController
    {
        protected $entityManager;

        protected function createForm($type, $data = null, array $options = [])
        {
            return new class {
                public function createView() { return null; }
                public function handleRequest($request) {}
                public function isSubmitted() { return false; }
                public function isValid() { return false; }
            };
        }

        protected function addFlash($type, $message): void {}

        protected function addSuccess($message, $context = 'front'): void {}

        protected function redirectToRoute($route, array $params = []) {}

        protected function render($template, array $params = []) { return $params; }
    }
}

// ---------------------------------------------------------------------------
// EC-CUBE Repository
// ---------------------------------------------------------------------------
namespace Eccube\Repository {
    abstract class AbstractRepository extends \Doctrine\ORM\EntityRepository
    {
    }

    class LayoutRepository extends AbstractRepository {}
    class ProductRepository extends AbstractRepository {}
}

// ---------------------------------------------------------------------------
// EC-CUBE Service
// ---------------------------------------------------------------------------
namespace Eccube\Service {
    class CartService
    {
        public function addProduct($productClass, $quantity): void {}
        public function getCart() { return null; }
        public function save(): void {}
    }
}

namespace Eccube\Service\PurchaseFlow {
    class PurchaseContext {}

    class PurchaseError
    {
        private $message;

        public function __construct(string $message)
        {
            $this->message = $message;
        }

        public function getMessage(): string
        {
            return $this->message;
        }
    }

    class PurchaseFlowResult
    {
        private bool $hasError;
        private array $errors;

        public function __construct(bool $hasError = false, array $errors = [])
        {
            $this->hasError = $hasError;
            $this->errors = $errors;
        }

        public function hasError(): bool
        {
            return $this->hasError;
        }

        public function getErrors(): array
        {
            return $this->errors;
        }
    }

    class PurchaseFlow
    {
        public function validate($cart, $context): PurchaseFlowResult
        {
            return new PurchaseFlowResult(false);
        }

        public function commit($cart, $context): void {}
    }
}
