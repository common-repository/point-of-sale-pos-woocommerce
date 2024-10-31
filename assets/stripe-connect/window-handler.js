(function () {
	const BASE_URL_SSO = 'https://login.bizswoop.app';

	const WINDOW_NAME = 'ServiceAuthGateway';

	const STRIPE_CONNECTION_STATUS_TYPE = 'stripe-connect-status';

	let popUpWindow = null;

	document.addEventListener('DOMContentLoaded', function () {
		document.body.addEventListener('click', function (event) {
			const ssoStripeConnectLink = event.target.closest('#ssoStripeConnect');

			if (!ssoStripeConnectLink) return;

			handleLinkClick(event);
		});

		window.addEventListener('message', handlePostMessages, false);
	});

	function sendPostMessage(message) {
		if (!popUpWindow) return;

		popUpWindow.postMessage(message, BASE_URL_SSO);
	}

	function handleLinkClick(event) {
		event.preventDefault();

		const url = new URL(`service-auth/register`, BASE_URL_SSO);

		url.searchParams.set('authMode', 'register');
		url.searchParams.set('service', 'pos-stripe-connect');
		url.searchParams.set('product', 'pos');
		url.searchParams.set('usePM', true);

		popUpWindow = openCenteredWindow(url.href, WINDOW_NAME, 550, 620);
	}

	async function handlePostMessages(event) {
		const { origin, data } = event;

		if (origin !== BASE_URL_SSO) return;

		if (data.type === 'content-loaded' && popUpWindow) {
			sendPostMessage({ type: 'parent-origin' });
			return;
		}

		if (data.type === 'stripe-connect-setup') {
			await setupAndVerifyStripeConnection(data);
			return;
		}
	}

	function openCenteredWindow(url, title, width, height) {
		const screenLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
		const screenTop = window.screenTop !== undefined ? window.screenTop : window.screenY;

		const innerWidth = window.innerWidth
			? window.innerWidth
			: document.documentElement.clientWidth
			? document.documentElement.clientWidth
			: window.screen.width;

		const innerHeight = window.innerHeight
			? window.innerHeight
			: document.documentElement.clientHeight
			? document.documentElement.clientHeight
			: window.screen.height;

		const left = (innerWidth - width) / 2 + screenLeft;
		const top = (innerHeight - height) / 2 + screenTop;

		const options = `width=${width},height=${height},top=${top},left=${left}`;

		const newWindow = window.open(url, title, options);

		if (window.focus && newWindow) newWindow.focus();

		return newWindow;
	}

	async function setupAndVerifyStripeConnection({ publicKey, secretKey }) {
		const { ajaxurl } = window.zpos_sso_stripe_connect_handler || {};

		if (!ajaxurl) return;

		const params = new URLSearchParams();

		params.set('action', 'zpos_check_stripe_connect_status');
		if (publicKey) params.set('zpos_stripe_public_key', publicKey);
		if (secretKey) params.set('zpos_stripe_secret_key', secretKey);

		const url = `${ajaxurl}?${params.toString()}`;

		try {
			const response = await fetch(url);

			if (!response.ok) throw new Error('Network response wasn`t ok');

			const data = await response.json();

			const message = data.is_connected
				? 'Stripe account successfully connected! Payments enabled for processing'
				: 'Failed to validate Stripe keys. Contact us for help and troubleshooting at support@bizswoop.com';

			sendPostMessage({ type: STRIPE_CONNECTION_STATUS_TYPE, success: data.is_connected, message });

			if (data.is_connected && popUpWindow) {
				setTimeout(() => {
					popUpWindow.close();
					window.location.reload();
				}, 5000);
			}
		} catch (error) {
			sendPostMessage({
				type: STRIPE_CONNECTION_STATUS_TYPE,
				success: false,
				message: 'Unable to connect. Please try again later or Contact us for help and troubleshooting at support@bizswoop.com',
			});
		}
	}
})();
