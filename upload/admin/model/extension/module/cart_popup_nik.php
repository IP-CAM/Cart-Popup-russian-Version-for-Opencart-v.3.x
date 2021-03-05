<?php
class ControllerExtensionModuleCartPopupNik extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/cart_popup_nik');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_cart_popup', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/cart_popup_nik', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/cart_popup_nik', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $this->load->language('extension/extension/module');
        $this->load->model('setting/extension');
        $this->load->model('setting/module');
        $data['categories'] = array();

        $extensions = $this->model_setting_extension->getInstalled('module');

        $files = glob(DIR_APPLICATION . 'controller/extension/module/*.php');

        if ($files) {
            foreach ($files as $file) {
                $extension = basename($file, '.php');

                $this->load->language('extension/module/' . $extension, 'extension');

                $module_data = array();

                $modules = $this->model_setting_module->getModulesByCode($extension);

                foreach ($modules as $module) {
                    if ($module['setting']) {
                        $setting_info = json_decode($module['setting'], true);
                    } else {
                        $setting_info = array();
                    }

                    $module_data[] = array(
                        'module_id' => $module['module_id'],
                        'name'      => $module['name'],
                        'status'    => (isset($setting_info['status']) && $setting_info['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                        'edit'      => $this->url->link('extension/module/' . $extension, 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module['module_id'], true),
                        'delete'    => $this->url->link('extension/extension/module/delete', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module['module_id'], true)
                    );
                }

                $data['extensions'][] = array(
                    'name'      => $this->language->get('extension')->get('heading_title'),
                    'status'    => $this->config->get('module_' . $extension . '_status') ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                    'module'    => $module_data,
                    'code'      => $extension,
                    'install'   => $this->url->link('extension/extension/module/install', 'user_token=' . $this->session->data['user_token'] . '&extension=' . $extension, true),
                    'uninstall' => $this->url->link('extension/extension/module/uninstall', 'user_token=' . $this->session->data['user_token'] . '&extension=' . $extension, true),
                    'installed' => in_array($extension, $extensions),
                    'edit'      => $this->url->link('extension/module/' . $extension, 'user_token=' . $this->session->data['user_token'], true)
                );
            }
        }

        $sort_order = array();

        foreach ($data['extensions'] as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $data['extensions']);

        if (isset($this->request->post['module_cart_popup_name'])) {
            $data['module_cart_popup_name'] = $this->request->post['module_cart_popup_name'];
        } else {
            $data['module_cart_popup_name'] = $this->config->get('module_cart_popup_name');
        }

        if (isset($this->request->post['module_cart_popup_display_heading'])) {
            $data['module_cart_popup_display_heading'] = $this->request->post['module_cart_popup_display_heading'];
        } else {
            $data['module_cart_popup_display_heading'] = $this->config->get('module_cart_popup_display_heading');
        }

        if (isset($this->request->post['module_cart_popup_button_class'])) {
            $data['module_cart_popup_button_class'] = $this->request->post['module_cart_popup_button_class'];
        } else {
            $data['module_cart_popup_button_class'] = $this->config->get('module_cart_popup_button_class');
        }

        if (isset($this->request->post['module_cart_popup_displayed_modules'])) {
            $displayed_modules = $this->request->post['module_cart_popup_displayed_modules'];
        } else {
            $displayed_modules = $this->config->get('module_cart_popup_displayed_modules');
        }

        foreach ($displayed_modules as $module) {
            if (intval($module)) {
                $module_info = $this->model_setting_module->getModule(intval($module[0]));
                $data['module_cart_popup_displayed_modules'][] = array(
                    'module_id' => $module,
                    'name'      => $module_info['name']
                );
            } else {
                $this->load->model('extension/module/cart_popup_nik');
                $extension_module = $this->model_extension_module_cart_popup_nik->getExtensionByCode($module);
                var_dump($extension_module);
//                $data['module_cart_popup_displayed_modules'][] = array(
//                    'module_id' => $module,
//                    'name'      => $module_info['name']
//                );
            }
//            $module_info = $this->model_catalog_category->getCategory($category['id']);
//
//            if ($category_info) {
//                $data['displayed_categories'][] = array(
//                    'category_id' => $category['id'],
//                    'name'        => $category_info['name'],
//                    'image'       => $category['image'],
//                    'thumb'       => !empty($category['image']) ? $this->model_tool_image->resize($category['image'], 40, 40) : $this->model_tool_image->resize('no_image.png', 40, 40)
//                );
//            }

        }

//        var_dump($data['module_cart_popup_displayed_modules']);

		if (isset($this->request->post['module_cart_popup_status'])) {
			$data['module_cart_popup_status'] = $this->request->post['module_cart_popup_status'];
		} else {
			$data['module_cart_popup_status'] = $this->config->get('module_cart_popup_status');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/cart_popup_nik', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/cart_popup_nik')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}