<?php
class ControllerExtensionModuleCartPopupNik extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/cart_popup_nik');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_cart_popup_nik', $this->request->post);
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

        $data['extensions'] = array();

        $extensions = $this->model_setting_extension->getInstalled('module');

        // Add all the modules which have multiple settings for each module
        foreach ($extensions as $code) {
            $this->load->language('extension/module/' . $code, 'extension');

            $module_data = array();

            $modules = $this->model_setting_module->getModulesByCode($code);

            foreach ($modules as $module) {
                $module_data[] = array(
                    'name' => strip_tags($module['name']),
                    'code' => $code . '.' .  $module['module_id']
                );
            }

            if ($this->config->has('module_' . $code . '_status') || $module_data) {
                $data['extensions'][] = array(
                    'name'   => strip_tags($this->language->get('extension')->get('heading_title')),
                    'code'   => $code,
                    'module' => $module_data
                );
            }
        }


        if (isset($this->request->post['module_cart_popup_nik_name'])) {
            $data['module_cart_popup_nik_name'] = $this->request->post['module_cart_popup_nik_name'];
        } else {
            $data['module_cart_popup_nik_name'] = $this->config->get('module_cart_popup_nik_name');
        }

        if (isset($this->request->post['module_cart_popup_nik_display_heading'])) {
            $data['module_cart_popup_nik_display_heading'] = $this->request->post['module_cart_popup_nik_display_heading'];
        } else {
            $data['module_cart_popup_nik_display_heading'] = $this->config->get('module_cart_popup_nik_display_heading');
        }

        if (isset($this->request->post['module_cart_popup_nik_button_class'])) {
            $data['module_cart_popup_nik_button_class'] = $this->request->post['module_cart_popup_nik_button_class'];
        } else {
            $data['module_cart_popup_nik_button_class'] = $this->config->get('module_cart_popup_nik_button_class');
        }

        if (isset($this->request->post['module_cart_popup_nik_displayed_modules'])) {
            $displayed_modules = $this->request->post['module_cart_popup_nik_displayed_modules'];
        } else {
            $displayed_modules = $this->config->get('module_cart_popup_nik_displayed_modules');
        }

        if($displayed_modules) {
            foreach ($displayed_modules as $module) {
                if($module) {
                    $part = explode('.', $module);
                    $this->load->language('extension/module/' . $part[0], 'extension');
                    $data['module_cart_popup_nik_displayed_modules'][] = array(
                        'code' => $module,
                        'name' => strip_tags($this->language->get('extension')->get('heading_title'))
                    );
                }

            }
        }

		if (isset($this->request->post['module_cart_popup_nik_status'])) {
			$data['module_cart_popup_nik_status'] = $this->request->post['module_cart_popup_nik_status'];
		} else {
			$data['module_cart_popup_nik_status'] = $this->config->get('module_cart_popup_nik_status');
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