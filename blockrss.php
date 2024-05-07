<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use GuzzleHttp\Client;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Blockrss
 */
class Blockrss extends Module
{

    private static $xmlFields = ['title', 'guid', 'description', 'author', 'comments', 'pubDate', 'source', 'link', 'content'];

    const RSS_FIELD_TITLE = 'RSS_FIELD_TITLE';
    const RSS_FIELD_URL = 'RSS_FIELD_URL';
    const RSS_FIELD_NBR = 'RSS_FIELD_NBR';

    /**
     * Blockrss constructor.
     */
    public function __construct()
    {
        $this->name = 'blockrss';
        $this->tab = 'front_office_features';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('RSS feed block');
        $this->description = $this->l('Adds a block displaying a RSS feed.');

        $this->version = '2.1.1';
        $this->author = 'thirty bees';
        $this->error = false;
        $this->valid = false;
        $this->tb_min_version = '1.0.0';
        $this->tb_versions_compliancy = '>= 1.0.0';
    }

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        Configuration::updateValue(self::RSS_FIELD_URL, $this->l('RSS feed'));
        Configuration::updateValue(self::RSS_FIELD_NBR, 5);

        return ($this->registerHook('header') && $this->registerHook('displayLeftColumn'));
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitBlockRss')) {
            $errors = [];
            $title = Tools::getValue(static::RSS_FIELD_TITLE);
            $urlfeed = Tools::getValue(static::RSS_FIELD_URL);
            $nbr = (int) Tools::getValue(static::RSS_FIELD_NBR);

            $guzzle = new Client([
                'verify'  => false,
                'timeout' => 30,
            ]);
            try {
                $contents = (string) $guzzle->get($urlfeed)->getBody();
            } catch (Exception $e) {
                $contents = false;
            }

            if ($urlfeed && !Validate::isAbsoluteUrl($urlfeed)) {
                $errors[] = $this->l('Invalid feed URL');
            } elseif (!$title || empty($title) || !Validate::isGenericName($title)) {
                $errors[] = $this->l('Invalid title');
            } elseif (!$nbr || $nbr <= 0 || !Validate::isInt($nbr)) {
                $errors[] = $this->l('Invalid number of feeds');
            } elseif (stristr($urlfeed, $_SERVER['HTTP_HOST'].__PS_BASE_URI__)) {
                $errors[] = $this->l('You have selected a feed URL from your own website. Please choose another URL.');
            } elseif (!$contents) {
                $errors[] = $this->l('Feed is unreachable, check your URL');
            } else {
                /* Even if the feed was reachable, We need to make sure that the feed is well-formatted */
                if (!@simplexml_load_string($contents)) {
                    $errors[] = $this->l('Invalid feed:').' '.implode(', ', libxml_get_errors());
                }

            }

            if (!sizeof($errors)) {
                Configuration::updateValue(static::RSS_FIELD_TITLE, $title);
                Configuration::updateValue(static::RSS_FIELD_URL, $urlfeed);
                Configuration::updateValue(static::RSS_FIELD_NBR, $nbr);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                $output .= $this->displayError(implode('<br />', $errors));
            }
        } else {
            $errors = [];
            if (stristr(Configuration::get(static::RSS_FIELD_URL), $_SERVER['HTTP_HOST'].__PS_BASE_URI__)) {
                $errors[] = $this->l('You have selected a feed URL from your own website. Please choose another URL.');
            }

            if (sizeof($errors)) {
                $output .= $this->displayError(implode('<br />', $errors));
            }
        }

        return $output.$this->renderForm();
    }

    /**
     * Render form
     *
     * @return string
     */
    public function renderForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Block title'),
                        'name'  => static::RSS_FIELD_TITLE,
                        'desc'  => $this->l('Create a title for the block (default: \'RSS feed\').'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Add a feed URL'),
                        'name'  => static::RSS_FIELD_URL,
                        'desc'  => $this->l('Add the URL of the feed you want to use (sample: http://news.google.com/?output=rss).'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Number of threads displayed'),
                        'name'  => static::RSS_FIELD_NBR,
                        'class' => 'fixed-width-sm',
                        'desc'  => $this->l('Number of threads displayed in the block (default value: 5).'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBlockRss';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Get config field values
     *
     * @return array
     */
    public function getConfigFieldsValues()
    {
        return [
            static::RSS_FIELD_TITLE => Tools::getValue(static::RSS_FIELD_TITLE, Configuration::get(static::RSS_FIELD_TITLE)),
            static::RSS_FIELD_URL   => Tools::getValue(static::RSS_FIELD_URL, Configuration::get(static::RSS_FIELD_URL)),
            static::RSS_FIELD_NBR   => Tools::getValue(static::RSS_FIELD_NBR, Configuration::get(static::RSS_FIELD_NBR)),
        ];
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayRightColumn($params)
    {
        return $this->hookDisplayLeftColumn($params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayLeftColumn($params)
    {
        // Conf
        $title = (string) Configuration::get(static::RSS_FIELD_TITLE);
        $url = (string) Configuration::get(static::RSS_FIELD_URL);
        $nb = (int) (Configuration::get(static::RSS_FIELD_NBR));

        $cacheId = $this->getCacheId($this->name.'-'.date('YmdH'));
        if (!$this->isCached('blockrss.tpl', $cacheId)) {
            // Getting data
            $rssLinks = [];
            $guzzle = new Client([
                'verify'  => false,
                'timeout' => 30,
            ]);
            try {
                $contents = (string) $guzzle->get($url)->getBody();
            } catch (Exception $e) {
                $contents = false;
            }

            if ($url && $contents) {
                if (@$src = simplexml_load_string($contents)) {
                    $items = $src->xpath('//channel/item');
                    for ($i = 0; $i < ($nb ? $nb : 5); $i++) {
                        if (isset($items[$i]) && $item = $items[$i]) {
                            /** @var SimpleXMLElement $item */
                            $xmlValues = [];

                            if (isset($item->enclosure)) {
                                /** @var SimpleXMLElement $enclosure */
                                $enclosure = $item->enclosure;
                                if (isset($enclosure->attributes()['url'])) {
                                    $xmlValues['enclosure'] = (string) $enclosure->attributes()['url'];
                                }
                            }

                            foreach (static::$xmlFields as $xmlField) {
                                if (isset($item->$xmlField)) {
                                    $xmlValues[$xmlField] = (string) $item->$xmlField;
                                }
                            }

                            // Compatibility
                            if (isset($item->link)) {
                                $xmlValues['url'] = (string) $item->link;

                            }

                            $rssLinks[] = $xmlValues;
                        }
                    }
                } else {
                    Tools::dieOrLog(sprintf($this->l('Error: invalid RSS feed in "blockrss" module: %s'), implode(',', libxml_get_errors())), false);
                }
            }

            // Display smarty
            $this->smarty->assign(['title' => ($title ? $title : $this->l('RSS feed')), 'rss_links' => $rssLinks]);
        }

        return $this->display(__FILE__, 'blockrss.tpl', $cacheId);
    }

    /**
     * @param array $params
     */
    function hookHeader($params)
    {
        $this->context->controller->addCSS(($this->_path).'blockrss.css', 'all');
    }
}
