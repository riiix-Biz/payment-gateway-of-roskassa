<?php
class ControllerExtensionPaymentRk extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/rk');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_rk', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');

		$data['entry_account'] = $this->language->get('entry_account');
		$data['entry_secret'] = $this->language->get('entry_secret');
		$data['entry_test'] = $this->language->get('entry_test');

		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['help_secret'] = $this->language->get('help_secret');
		$data['help_total'] = $this->language->get('help_total');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['account'])) {
			$data['error_account'] = $this->error['account'];
		} else {
			$data['error_account'] = '';
		}

		if (isset($this->error['secret'])) {
			$data['error_secret'] = $this->error['secret'];
		} else {
			$data['error_secret'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/rk', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/rk', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data['has_ssl'] = !empty($this->request->server['HTTPS']);

		if (isset($this->request->post['payment_rk_account'])) {
			$data['payment_rk_account'] = $this->request->post['payment_rk_account'];
		} else {
			$data['payment_rk_account'] = $this->config->get('payment_rk_account');
		}

		if (isset($this->request->post['payment_rk_secret'])) {
			$data['payment_rk_secret'] = $this->request->post['payment_rk_secret'];
		} else {
			$data['payment_rk_secret'] = $this->config->get('payment_rk_secret');
		}

		if (isset($this->request->post['payment_rk_order_status_id'])) {
			$data['payment_rk_order_status_id'] = $this->request->post['payment_rk_order_status_id'];
		} else {
			$data['payment_rk_order_status_id'] = $this->config->get('payment_rk_order_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_rk_status'])) {
			$data['payment_rk_status'] = $this->request->post['payment_rk_status'];
		} else {
			$data['payment_rk_status'] = $this->config->get('payment_rk_status');
		}

		if (isset($this->request->post['payment_rk_sort_order'])) {
			$data['payment_rk_sort_order'] = $this->request->post['payment_rk_sort_order'];
		} else {
			$data['payment_rk_sort_order'] = $this->config->get('payment_rk_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/rk', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/rk')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_rk_account']) {
			$this->error['account'] = $this->language->get('error_account');
		}

		if (!$this->request->post['payment_rk_secret']) {
			$this->error['secret'] = $this->language->get('error_secret');
		}

		return !$this->error;
	}
}