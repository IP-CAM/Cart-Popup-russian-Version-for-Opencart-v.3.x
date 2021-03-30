<?php
class ControllerExtensionModuleCartPopupNik extends Controller {
	public function index() {
	    $data = $this->getPopupCart();
        return $this->load->view('extension/module/cart_popup_nik', $data);

	}

	public function update() {
	    if (isset($this->request->get['cart_id']) && isset($this->request->get['quantity'])) {
            $this->cart->update($this->request->get['cart_id'], $this->request->get['quantity']);
        }
        $data = $this->getPopupCart();
        $this->response->setOutput($this->load->view('extension/module/cart_popup_nik', $data));
    }

    private function getPopupCart() {
        $this->load->language('extension/module/cart_popup_nik');

        $this->load->model('setting/setting');

        $data = $this->model_setting_setting->getSetting('module_cart_popup_nik');

        $this->load->model('catalog/product');

        $this->load->model('tool/image');
        $this->load->model('tool/upload');

        $data['products'] = array();
        $not_in_stock = 0;
        $product_count = 0;
        foreach ($this->cart->getProducts() as $product) {
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);
            $product_category_info = $this->model_catalog_product->getCategories($product['product_id']);

            if ($product['image']) {
                $image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
            } else {
                $image = '';
            }

            $option_data = array();

            foreach ($product['option'] as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }

                $option_data[] = array(
                    'name' => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value),
                    'type' => $option['type']
                );

            }

            if ($product_info['weight']) {
                $weight = $this->weight->format($product_info['weight'], $product_info['weight_class_id'], $this->language->get('decimal_point'), $this->language->get('thousand_point'));
            } else {
                $weight = '';
            }

            // Display prices
            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));


                if ((float)$product_info['special']) {
                    $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                    $price = $this->currency->format($product_info['price'], $this->session->data['currency']);
                    $total = $this->currency->format($product_info['price'] * $product['quantity'], $this->session->data['currency']);
                } else {
                    $special = false;
                    $price = $this->currency->format($unit_price, $this->session->data['currency']);
                    $total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
                }
            } else {
                $price = false;
                $total = false;
                $special = false;
            }

            $data['products'][] = array(
                'cart_id'   => $product['cart_id'],
                'thumb'     => $image,
                'product_id'=> $product['product_id'],
                'name'      => $product['name'],
                'model'     => $product['model'],
                'option'    => $option_data,
                'recurring' => ($product['recurring'] ? $product['recurring']['name'] : ''),
                'quantity'  => $product['quantity'],
                'stock'     => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
                'category_link' => isset($product_category_info[0]) ? !$product['stock'] ? $this->url->link('product/category', '&path=' . $product_category_info[0]['category_id'], true) : '' : '',
                'price'     => $price,
                'special'   => $special,
                'weight'    => $weight,
                'total'     => $total,
                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            );
            if (!$product['stock']) {
                $not_in_stock++;
            }
            $product_count++;
        }

        // Gift Voucher
        $data['vouchers'] = array();

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $key => $voucher) {
                $data['vouchers'][] = array(
                    'key'         => $key,
                    'description' => $voucher['description'],
                    'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency']),
                    'remove'      => $this->url->link('checkout/cart', 'remove=' . $key)
                );
            }
        }

        $this->load->model('setting/module');
        $data['modules'] = array();

        if(isset($data['module_cart_popup_nik_displayed_modules'])) {
            foreach ($data['module_cart_popup_nik_displayed_modules'] as $module) {
                $part = explode('.', $module);

                if (isset($part[0]) && $this->config->get('module_' . $part[0] . '_status')) {
                    $module_data = $this->load->controller('extension/module/' . $part[0]);

                    if ($module_data) {
                        $data['modules'][] = $module_data;
                    }
                }

                if (isset($part[1])) {
                    $setting_info = $this->model_setting_module->getModule($part[1]);

                    if ($setting_info && $setting_info['status']) {
                        $output = $this->load->controller('extension/module/' . $part[0], $setting_info);

                        if ($output) {
                            $data['modules'][] = $output;
                        }
                    }
                }
            }
        }

        // Totals
        $this->load->model('setting/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        // Display prices
        if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
            $sort_order = array();

            $results = $this->model_setting_extension->getExtensions('total');

            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
            }

            array_multisort($sort_order, SORT_ASC, $results);

            foreach ($results as $result) {
                if ($this->config->get('total_' . $result['code'] . '_status')) {
                    $this->load->model('extension/total/' . $result['code']);

                    // We have to put the totals in an array so that they pass by reference.
                    $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                }
            }

            $sort_order = array();

            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);
        }

        $data['totals'] = array();

        foreach ($totals as $total) {
            if ($total['code'] == 'total') {
                $data['total'] = $this->currency->format($total['value'], $this->session->data['currency']);
            }
        }

        $json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
        $data['checkout'] = $this->url->link('checkout/checkout', '', true);
        $data['not_in_stock'] = $not_in_stock;
        $data['product_count'] = $product_count;

        return $data;
    }

    public function getSimilarProducts() {
        if (isset($this->request->get['product_id'])) {
            $this->load->model('catalog/product');
            $this->load->model('extension/module/cart_popup_nik');
            $this->load->model('tool/image');
            $this->load->model('tool/upload');

            $product_to_replace_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
            $product_category_info   = $this->model_catalog_product->getCategories($this->request->get['product_id']);

            $product_to_replace_price = $product_to_replace_info['special'] ? $product_to_replace_info['special'] : $product_to_replace_info['price'];

            $price_deviation_min = ($product_to_replace_price > 51) ? ($product_to_replace_price - 50) : $product_to_replace_price;
            $price_deviation_max = $product_to_replace_price + 50;

            $category_id = isset($product_category_info[0]) ? $product_category_info[0]['category_id'] : array();

            $json = array();

            if (!empty($category_id)) {

                $filter_data = array(
                    'filter_category_id' => $category_id
                );

                $products = $this->model_extension_module_cart_popup_nik->getProducts($filter_data);

                foreach ($products as $product) {
                    $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                    if ($product['image']) {
                        $image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
                    } else {
                        $image = '';
                    }

                    if ($product_info['weight']) {
                        $weight = $this->weight->format($product_info['weight'], $product_info['weight_class_id'], $this->language->get('decimal_point'), $this->language->get('thousand_point'));
                    } else {
                        $weight = '';
                    }

                    // Display prices
                    if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                        $unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

                        if ((float)$product_info['special']) {
                            $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                            $price = $this->currency->format($product_info['price'], $this->session->data['currency']);
                            $total = $this->currency->format($product_info['price'] * $product['quantity'], $this->session->data['currency']);
                        } else {
                            $special = false;
                            $price = $this->currency->format($unit_price, $this->session->data['currency']);
                            $total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
                        }
                    } else {
                        $price = false;
                        $total = false;
                        $special = false;
                    }

                    if ($product['special']) {
                        if ($product['special'] > $price_deviation_min && $product['special'] < $price_deviation_max) {
                            $json['products'][] = array(
                                'thumb'     => $image,
                                'product_id'=> $product['product_id'],
                                'name'      => $product['name'],
                                'quantity'  => $product['quantity'],
                                'minimum'   => $product['minimum'],
                                'price'     => $price,
                                'special'   => $special,
                                'weight'    => $weight,
                                'total'     => $total,
                                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                            );
                        }
                    } else {
                        if ($product['price'] > $price_deviation_min && $product['price'] < $price_deviation_max) {
                            $json['products'][] = array(
                                'thumb'     => $image,
                                'product_id'=> $product['product_id'],
                                'name'      => $product['name'],
                                'quantity'  => $product['quantity'],
                                'minimum'   => $product['minimum'],
                                'price'     => $price,
                                'special'   => $special,
                                'weight'    => $weight,
                                'total'     => $total,
                                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                            );
                        }
                    }
                }

                $filter_data = array(
                    'filter_manufacturer_id' => $product_to_replace_info['manufacturer_id']
                );

                $products = $this->model_extension_module_cart_popup_nik->getProducts($filter_data);

                foreach ($products as $product) {
                    $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                    if ($product['image']) {
                        $image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
                    } else {
                        $image = '';
                    }

                    if ($product_info['weight']) {
                        $weight = $this->weight->format($product_info['weight'], $product_info['weight_class_id'], $this->language->get('decimal_point'), $this->language->get('thousand_point'));
                    } else {
                        $weight = '';
                    }

                    // Display prices
                    if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                        $unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

                        if ((float)$product_info['special']) {
                            $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                            $price = $this->currency->format($product_info['price'], $this->session->data['currency']);
                            $total = $this->currency->format($product_info['price'] * $product['quantity'], $this->session->data['currency']);
                        } else {
                            $special = false;
                            $price = $this->currency->format($unit_price, $this->session->data['currency']);
                            $total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
                        }
                    } else {
                        $price = false;
                        $total = false;
                        $special = false;
                    }

                    if ($product['special']) {
                        if ($product['special'] > $price_deviation_min && $product['special'] < $price_deviation_max) {
                            $json['products'][] = array(
                                'thumb'     => $image,
                                'product_id'=> $product['product_id'],
                                'name'      => $product['name'],
                                'quantity'  => $product['quantity'],
                                'minimum'   => $product['minimum'],
                                'price'     => $price,
                                'special'   => $special,
                                'weight'    => $weight,
                                'total'     => $total,
                                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                            );
                        }
                    } else {
                        if ($product['price'] > $price_deviation_min && $product['price'] < $price_deviation_max) {
                            $json['products'][] = array(
                                'thumb'     => $image,
                                'product_id'=> $product['product_id'],
                                'name'      => $product['name'],
                                'quantity'  => $product['quantity'],
                                'minimum'   => $product['minimum'],
                                'price'     => $price,
                                'special'   => $special,
                                'weight'    => $weight,
                                'total'     => $total,
                                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                            );
                        }
                    }
                }

                if (empty($json['products']) || count($json['products']) < 2) {
                    $filter_data = array(
                        'filter_category_id' => $category_id
                    );

                    $products = $this->model_extension_module_cart_popup_nik->getProducts($filter_data);

                    foreach ($products as $product) {
                        $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                        if ($product['image']) {
                            $image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
                        } else {
                            $image = '';
                        }

                        if ($product_info['weight']) {
                            $weight = $this->weight->format($product_info['weight'], $product_info['weight_class_id'], $this->language->get('decimal_point'), $this->language->get('thousand_point'));
                        } else {
                            $weight = '';
                        }

                        // Display prices
                        if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                            $unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

                            if ((float)$product_info['special']) {
                                $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                                $price = $this->currency->format($product_info['price'], $this->session->data['currency']);
                                $total = $this->currency->format($product_info['price'] * $product['quantity'], $this->session->data['currency']);
                            } else {
                                $special = false;
                                $price = $this->currency->format($unit_price, $this->session->data['currency']);
                                $total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
                            }
                        } else {
                            $price = false;
                            $total = false;
                            $special = false;
                        }


                        $json['products'][] = array(
                            'thumb'     => $image,
                            'product_id'=> $product['product_id'],
                            'name'      => $product['name'],
                            'quantity'  => $product['quantity'],
                            'minimum'   => $product['minimum'],
                            'price'     => $price,
                            'special'   => $special,
                            'weight'    => $weight,
                            'total'     => $total,
                            'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                        );
                    }
                }
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }
    }
}