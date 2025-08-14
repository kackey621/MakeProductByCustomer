<?php

namespace Plugin\MPBC43\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_mpbc_config")
 * @ORM\Entity(repositoryClass="Plugin\MPBC43\Repository\ConfigRepository")
 */
class Config
{
        /**
         * @var int
         *
         * @ORM\Column(name="id", type="integer", options={"unsigned":true})
         * @ORM\Id
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private $id;

        /**
         * @var string
         * 
         * @ORM\Column(name="product_name_display_type", type="string", length=50, nullable=false, options={"default":"customer_input"})
         */
        private $product_name_display_type = 'customer_input';

        /**
         * @var string
         * 
         * @ORM\Column(name="predefined_product_name", type="string", length=255, nullable=true)
         */
        private $predefined_product_name;

        /**
         * @var int
         * 
         * @ORM\Column(name="page_layout", type="integer", nullable=true)
         */
        private $page_layout;

        /**
         * @var string
         * 
         * @ORM\Column(name="page_title", type="string", length=255, nullable=true)
         */
        private $page_title;

        /**
         * @var string
         * 
         * @ORM\Column(name="page_description", type="text", nullable=true)
         */
        private $page_description;

        /**
         * @return int
         */
        public function getId()
        {
            return $this->id;
        }

        /**
         * @return string
         */
        public function getProductNameDisplayType()
        {
            return $this->product_name_display_type;
        }

        /**
         * @param string $product_name_display_type
         * @return $this
         */
        public function setProductNameDisplayType($product_name_display_type)
        {
            $this->product_name_display_type = $product_name_display_type;
            return $this;
        }

        /**
         * @return string|null
         */
        public function getPredefinedProductName()
        {
            return $this->predefined_product_name;
        }

        /**
         * @param string|null $predefined_product_name
         * @return $this
         */
        public function setPredefinedProductName($predefined_product_name)
        {
            $this->predefined_product_name = $predefined_product_name;
            return $this;
        }

        /**
         * @return int|null
         */
        public function getPageLayout()
        {
            return $this->page_layout;
        }

        /**
         * @param int|null $page_layout
         * @return $this
         */
        public function setPageLayout($page_layout)
        {
            $this->page_layout = $page_layout;
            return $this;
        }

        /**
         * @return string|null
         */
        public function getPageTitle()
        {
            return $this->page_title;
        }

        /**
         * @param string|null $page_title
         * @return $this
         */
        public function setPageTitle($page_title)
        {
            $this->page_title = $page_title;
            return $this;
        }

        /**
         * @return string|null
         */
        public function getPageDescription()
        {
            return $this->page_description;
        }

        /**
         * @param string|null $page_description
         * @return $this
         */
        public function setPageDescription($page_description)
        {
            $this->page_description = $page_description;
            return $this;
        }
    }
