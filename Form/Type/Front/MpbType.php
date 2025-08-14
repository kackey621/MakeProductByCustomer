<?php

namespace Plugin\MPBC43\Form\Type\Front;

use Eccube\Common\EccubeConfig;
use Plugin\MPBC43\Repository\ConfigRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class MpbType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * MpbType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     * @param ConfigRepository $configRepository
     */
    public function __construct(EccubeConfig $eccubeConfig, ConfigRepository $configRepository)
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->configRepository = $configRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // 設定を取得
        $config = $this->configRepository->get();
        $displayType = $config ? $config->getProductNameDisplayType() : 'customer_input';

        if ($displayType === 'customer_input') {
            // お客様が入力する場合
            $builder->add('product_name', TextType::class, [
                'label' => '取引(商品)名称',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                ],
            ]);
        } else {
            // 店側が設定した商品名を表示する場合（読み取り専用）
            $predefinedName = $config && $config->getPredefinedProductName() 
                ? $config->getPredefinedProductName() 
                : 'MPB_Product'; // デフォルトの商品名
            
            $builder->add('product_name', HiddenType::class, [
                'data' => $predefinedName,
            ]);
        }

        $builder->add('price', TextType::class, [
            'label' => '価格',
            'required' => true,
            'attr' => [
                'class' => 'price-input',
                'style' => '-webkit-appearance: none; -moz-appearance: textfield;',
                'autocomplete' => 'off',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*'
            ],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Regex([
                    'pattern' => '/^\d+$/',
                    'message' => '数値のみ入力してください。'
                ]),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'mpb';
    }
}