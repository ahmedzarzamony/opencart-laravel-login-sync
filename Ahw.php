<?php

trait Ahw{

    public function apiEndpoint()
    {
        return 'https://stg.arabhardware.com/';
    }

    public function dd($data = null)
    {
        echo '<pre>', print_r($data), '</pre>';die;
    }

	public function callExternalApi($apiUrl, $postData) {
		$apiUrl = $this->apiEndpoint() . $apiUrl;
		$ch = curl_init($apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1); // Set it as a POST request
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
		
		$apiResponse = curl_exec($ch);
		curl_close($ch);
		return json_decode($apiResponse);
	}

    public function ahw_register_validate_email($email) {
        return $this->callExternalApi('store/email_exists', [
            'email' => $email,
        ]);
    }

    public function ahw_login_validate() {
		// Check how many login attempts have been made.
		$login_info = $this->model_account_customer->getLoginAttempts($this->request->post['email']);

		if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
			$this->error['warning'] = $this->language->get('error_attempts');
		}

		// Check if customer has been approved.
		$customer_info = $this->model_account_customer->getCustomerByEmail($this->request->post['email']);

		if ($customer_info && !$customer_info['status']) {
			$this->error['warning'] = $this->language->get('error_approved');
		}

		if (!$this->error) {
			$check = $this->callExternalApi('store/login', [
                'email' => $this->request->post['email'],
                'password' => $this->request->post['password']
            ]);
			//$this->dd($check);
			if(!@$check->success){
				$this->error['warning'] = $this->language->get('error_login');
			}
			if (@$check->success && !$this->error) {
                $login = $this->customer->login($this->request->post['email'], $this->request->post['password']);
                if (@$check->success && !$login){
                    $this->doCartRegister(@$check->user, @$check->password);
                    $this->ahw_validate();
                }
				if (!$login) {
					$this->error['warning'] = $this->language->get('error_login');
	
					$this->model_account_customer->addLoginAttempt($this->request->post['email']);
				} else {
					$this->model_account_customer->deleteLoginAttempts($this->request->post['email']);
				}
			}
		}

		return !$this->error;
	}

    public function doCartRegister($user, $password)
    {
        if(!empty($user) || trim($password) == null){
            $postData = [];
            $postData['customer_group_id'] = 1;
            $postData['firstname'] = $user->fname ?? 'AHW';
            $postData['lastname'] = $user->lname ?? 'User';
            $postData['email'] = $user->email;
            $postData['telephone'] = '';
            $postData['password'] = $password;
            $postData['confirm'] = $password;
            $postData['newsletter'] = 0;
            $postData['agree'] = 1;
            $customer_id = $this->model_account_customer->addCustomer($postData);

			// Clear any previous login attempts for unregistered accounts.
			$this->model_account_customer->deleteLoginAttempts($postData['email']);

			$this->customer->login($postData['email'], $postData['password']);

			unset($this->session->data['guest']);

			$this->response->redirect($this->url->link('account/success'));
        }
    }

    public function ahw_store_register() {
        $register = $this->callExternalApi('store/register', [
            'fname' => $this->request->post['firstname'],
            'lname' => $this->request->post['lastname'],
            'email' => $this->request->post['email'],
            'password' => $this->request->post['password'],
            'active' => true,
        ]);
        if(!@$register->success){
            if(@$register->reason == 'email_exists'){
                $this->error['warning'] = $this->language->get('error_exists');
            }else if(@$register->reason == 'validation_error'){
                $this->error['email'] = $this->language->get('error_email');
            }else if(@$register->reason == 'unkown'){
                $this->error['warning'] = $this->language->get('error_exists');
            }
            return false;
        }
        return true;
    }
}