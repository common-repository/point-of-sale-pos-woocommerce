<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return [
	'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
	'ZPOS\\API' => $baseDir . '/includes/API.php',
	'ZPOS\\API\\Application' => $baseDir . '/includes/API/Application.php',
	'ZPOS\\API\\Applications' => $baseDir . '/includes/API/Applications.php',
	'ZPOS\\API\\Auth' => $baseDir . '/includes/API/Auth.php',
	'ZPOS\\API\\Cart' => $baseDir . '/includes/API/Cart.php',
	'ZPOS\\API\\Categories' => $baseDir . '/includes/API/Categories.php',
	'ZPOS\\API\\Coupons' => $baseDir . '/includes/API/Coupons.php',
	'ZPOS\\API\\Customers' => $baseDir . '/includes/API/Customers.php',
	'ZPOS\\API\\FrontEndSettings' => $baseDir . '/includes/API/FrontEndSettings.php',
	'ZPOS\\API\\Gateways' => $baseDir . '/includes/API/Gateways.php',
	'ZPOS\\API\\Groups' => $baseDir . '/includes/API/Groups.php',
	'ZPOS\\API\\OrderNotes' => $baseDir . '/includes/API/OrderNotes.php',
	'ZPOS\\API\\Orders' => $baseDir . '/includes/API/Orders.php',
	'ZPOS\\API\\PrintLocation' => $baseDir . '/includes/API/PrintLocation.php',
	'ZPOS\\API\\ProductTags' => $baseDir . '/includes/API/ProductTags.php',
	'ZPOS\\API\\ProductVariations' => $baseDir . '/includes/API/ProductVariations.php',
	'ZPOS\\API\\Products' => $baseDir . '/includes/API/Products.php',
	'ZPOS\\API\\Setting\\Option' => $baseDir . '/includes/API/Setting/Option.php',
	'ZPOS\\API\\Settings' => $baseDir . '/includes/API/Settings.php',
	'ZPOS\\API\\Shipping' => $baseDir . '/includes/API/Shipping.php',
	'ZPOS\\API\\Stations' => $baseDir . '/includes/API/Stations.php',
	'ZPOS\\API\\TaxClasses' => $baseDir . '/includes/API/TaxClasses.php',
	'ZPOS\\API\\Taxes' => $baseDir . '/includes/API/Taxes.php',
	'ZPOS\\API\\UserAccounts' => $baseDir . '/includes/API/UserAccounts.php',
	'ZPOS\\API\\Woocommerce\\Orders' => $baseDir . '/includes/API/Woocommerce/Orders.php',
	'ZPOS\\Activate' => $baseDir . '/includes/Activate.php',
	'ZPOS\\Admin' => $baseDir . '/includes/Admin.php',
	'ZPOS\\Admin\\Addons' => $baseDir . '/includes/Admin/Addons.php',
	'ZPOS\\Admin\\Analytics' => $baseDir . '/includes/Admin/Analytics.php',
	'ZPOS\\Admin\\Analytics\\Orders' => $baseDir . '/includes/Admin/Analytics/Orders.php',
	'ZPOS\\Admin\\Layout' => $baseDir . '/includes/Admin/Layout.php',
	'ZPOS\\Admin\\Menu' => $baseDir . '/includes/Admin/Menu.php',
	'ZPOS\\Admin\\Orders' => $baseDir . '/includes/Admin/Orders.php',
	'ZPOS\\Admin\\QuickStart' => $baseDir . '/includes/Admin/QuickStart.php',
	'ZPOS\\Admin\\Reports' => $baseDir . '/includes/Admin/Reports.php',
	'ZPOS\\Admin\\Reports\\ReportSalesByGateway' =>
		$baseDir . '/includes/Admin/Reports/ReportSalesByGateway.php',
	'ZPOS\\Admin\\Reports\\ReportSalesByOrderType' =>
		$baseDir . '/includes/Admin/Reports/ReportSalesByOrderType.php',
	'ZPOS\\Admin\\Reports\\ReportSalesByUser' =>
		$baseDir . '/includes/Admin/Reports/ReportSalesByUser.php',
	'ZPOS\\Admin\\Setting' => $baseDir . '/includes/Admin/Setting.php',
	'ZPOS\\Admin\\Setting\\Box' => $baseDir . '/includes/Admin/Setting/Box.php',
	'ZPOS\\Admin\\Setting\\CoreBox' => $baseDir . '/includes/Admin/Setting/CoreBox.php',
	'ZPOS\\Admin\\Setting\\InputBase' => $baseDir . '/includes/Admin/Setting/InputBase.php',
	'ZPOS\\Admin\\Setting\\Input\\ActionConfirmLink' =>
		$baseDir . '/includes/Admin/Setting/Input/ActionConfirmLink.php',
	'ZPOS\\Admin\\Setting\\Input\\AllOptionalFilter' =>
		$baseDir . '/includes/Admin/Setting/Input/AllOptionalFilter.php',
	'ZPOS\\Admin\\Setting\\Input\\AssocArray' =>
		$baseDir . '/includes/Admin/Setting/Input/AssocArray.php',
	'ZPOS\\Admin\\Setting\\Input\\Checkbox' =>
		$baseDir . '/includes/Admin/Setting/Input/Checkbox.php',
	'ZPOS\\Admin\\Setting\\Input\\ColorPicker' =>
		$baseDir . '/includes/Admin/Setting/Input/ColorPicker.php',
	'ZPOS\\Admin\\Setting\\Input\\ConnectionTypes' =>
		$baseDir . '/includes/Admin/Setting/Input/ConnectionTypes.php',
	'ZPOS\\Admin\\Setting\\Input\\Description' =>
		$baseDir . '/includes/Admin/Setting/Input/Description.php',
	'ZPOS\\Admin\\Setting\\Input\\DropdownSelect' =>
		$baseDir . '/includes/Admin/Setting/Input/DropdownSelect.php',
	'ZPOS\\Admin\\Setting\\Input\\GatewayArray' =>
		$baseDir . '/includes/Admin/Setting/Input/GatewayArray.php',
	'ZPOS\\Admin\\Setting\\Input\\Input' => $baseDir . '/includes/Admin/Setting/Input/Input.php',
	'ZPOS\\Admin\\Setting\\Input\\Media' => $baseDir . '/includes/Admin/Setting/Input/Media.php',
	'ZPOS\\Admin\\Setting\\Input\\MultipleSwitch' =>
		$baseDir . '/includes/Admin/Setting/Input/MultipleSwitch.php',
	'ZPOS\\Admin\\Setting\\Input\\NotificationsSettings' =>
		$baseDir . '/includes/Admin/Setting/Input/NotificationsSettings.php',
	'ZPOS\\Admin\\Setting\\Input\\Number' => $baseDir . '/includes/Admin/Setting/Input/Number.php',
	'ZPOS\\Admin\\Setting\\Input\\PluginWidgets' =>
		$baseDir . '/includes/Admin/Setting/Input/PluginWidgets.php',
	'ZPOS\\Admin\\Setting\\Input\\Radio' => $baseDir . '/includes/Admin/Setting/Input/Radio.php',
	'ZPOS\\Admin\\Setting\\Input\\RadioWithOptions' =>
		$baseDir . '/includes/Admin/Setting/Input/RadioWithOptions.php',
	'ZPOS\\Admin\\Setting\\Input\\Select' => $baseDir . '/includes/Admin/Setting/Input/Select.php',
	'ZPOS\\Admin\\Setting\\Input\\SwitchInput' =>
		$baseDir . '/includes/Admin/Setting/Input/SwitchInput.php',
	'ZPOS\\Admin\\Setting\\Input\\TaxArray' =>
		$baseDir . '/includes/Admin/Setting/Input/TaxArray.php',
	'ZPOS\\Admin\\Setting\\Input\\TextArea' =>
		$baseDir . '/includes/Admin/Setting/Input/TextArea.php',
	'ZPOS\\Admin\\Setting\\Input\\UserRights' =>
		$baseDir . '/includes/Admin/Setting/Input/UserRights.php',
	'ZPOS\\Admin\\Setting\\Page' => $baseDir . '/includes/Admin/Setting/Page.php',
	'ZPOS\\Admin\\Setting\\PageTab' => $baseDir . '/includes/Admin/Setting/PageTab.php',
	'ZPOS\\Admin\\Setting\\Post' => $baseDir . '/includes/Admin/Setting/Post.php',
	'ZPOS\\Admin\\Setting\\PostTab' => $baseDir . '/includes/Admin/Setting/PostTab.php',
	'ZPOS\\Admin\\Setting\\Sanitize\\Boolean' =>
		$baseDir . '/includes/Admin/Setting/Sanitize/Boolean.php',
	'ZPOS\\Admin\\Setting\\Tab' => $baseDir . '/includes/Admin/Setting/Tab.php',
	'ZPOS\\Admin\\Stations\\Layout' => $baseDir . '/includes/Admin/Stations/Layout.php',
	'ZPOS\\Admin\\Stations\\MyAccount' => $baseDir . '/includes/Admin/Stations/MyAccount.php',
	'ZPOS\\Admin\\Stations\\Post' => $baseDir . '/includes/Admin/Stations/Post.php',
	'ZPOS\\Admin\\Stations\\Setting' => $baseDir . '/includes/Admin/Stations/Setting.php',
	'ZPOS\\Admin\\Stations\\Setup' => $baseDir . '/includes/Admin/Stations/Setup.php',
	'ZPOS\\Admin\\Stations\\Tabs\\Cart' => $baseDir . '/includes/Admin/Stations/Tabs/Cart.php',
	'ZPOS\\Admin\\Stations\\Tabs\\General' => $baseDir . '/includes/Admin/Stations/Tabs/General.php',
	'ZPOS\\Admin\\Stations\\Tabs\\Products' =>
		$baseDir . '/includes/Admin/Stations/Tabs/Products.php',
	'ZPOS\\Admin\\Stations\\Tabs\\Tax' => $baseDir . '/includes/Admin/Stations/Tabs/Tax.php',
	'ZPOS\\Admin\\Stations\\Tabs\\Users' => $baseDir . '/includes/Admin/Stations/Tabs/Users.php',
	'ZPOS\\Admin\\Stations\\Tabs\\Users\\AutoLogout' =>
		$baseDir . '/includes/Admin/Stations/Tabs/Users/AutoLogout.php',
	'ZPOS\\Admin\\Tabs\\Addons' => $baseDir . '/includes/Admin/Tabs/Addons.php',
	'ZPOS\\Admin\\Tabs\\Connection' => $baseDir . '/includes/Admin/Tabs/Connection.php',
	'ZPOS\\Admin\\Tabs\\Debug' => $baseDir . '/includes/Admin/Tabs/Debug.php',
	'ZPOS\\Admin\\Tabs\\Gateway' => $baseDir . '/includes/Admin/Tabs/Gateway.php',
	'ZPOS\\Admin\\Tabs\\General' => $baseDir . '/includes/Admin/Tabs/General.php',
	'ZPOS\\Admin\\Tabs\\Users' => $baseDir . '/includes/Admin/Tabs/Users.php',
	'ZPOS\\Admin\\Tabs\\Users\\Access' => $baseDir . '/includes/Admin/Tabs/Users/Access.php',
	'ZPOS\\Admin\\Tabs\\Users\\Multiple' => $baseDir . '/includes/Admin/Tabs/Users/Multiple.php',
	'ZPOS\\Admin\\Tabs\\Users\\UserSettings' =>
		$baseDir . '/includes/Admin/Tabs/Users/UserSettings.php',
	'ZPOS\\Admin\\User' => $baseDir . '/includes/Admin/User.php',
	'ZPOS\\Admin\\Woocommerce' => $baseDir . '/includes/Admin/Woocommerce.php',
	'ZPOS\\Admin\\Woocommerce\\Categories' => $baseDir . '/includes/Admin/Woocommerce/Categories.php',
	'ZPOS\\Admin\\Woocommerce\\Products' => $baseDir . '/includes/Admin/Woocommerce/Products.php',
	'ZPOS\\Auth' => $baseDir . '/includes/Auth.php',
	'ZPOS\\Deactivate' => $baseDir . '/includes/Deactivate.php',
	'ZPOS\\Emails' => $baseDir . '/includes/Emails.php',
	'ZPOS\\Emails\\Receipt' => $baseDir . '/includes/Emails/Receipt.php',
	'ZPOS\\Frontend' => $baseDir . '/includes/Frontend.php',
	'ZPOS\\Gateway\\BankTransfer' => $baseDir . '/includes/Gateway/BankTransfer.php',
	'ZPOS\\Gateway\\Base' => $baseDir . '/includes/Gateway/Base.php',
	'ZPOS\\Gateway\\Cash' => $baseDir . '/includes/Gateway/Cash.php',
	'ZPOS\\Gateway\\CashDelivery' => $baseDir . '/includes/Gateway/CashDelivery.php',
	'ZPOS\\Gateway\\Check' => $baseDir . '/includes/Gateway/Check.php',
	'ZPOS\\Gateway\\ChipPin' => $baseDir . '/includes/Gateway/ChipPin.php',
	'ZPOS\\Gateway\\EPD' => $baseDir . '/includes/Gateway/EPD.php',
	'ZPOS\\Gateway\\GiftCard' => $baseDir . '/includes/Gateway/GiftCard.php',
	'ZPOS\\Gateway\\QRCode' => $baseDir . '/includes/Gateway/QRCode.php',
	'ZPOS\\Gateway\\Smart' => $baseDir . '/includes/Gateway/Smart.php',
	'ZPOS\\Gateway\\SplitPayment' => $baseDir . '/includes/Gateway/SplitPayment.php',
	'ZPOS\\Gateway\\Stripe' => $baseDir . '/includes/Gateway/Stripe.php',
	'ZPOS\\Gateway\\Stripe\\API' => $baseDir . '/includes/Gateway/Stripe/API.php',
	'ZPOS\\Login' => $baseDir . '/includes/Login.php',
	'ZPOS\\Model' => $baseDir . '/includes/Model.php',
	'ZPOS\\Model\\BillingVat' => $baseDir . '/includes/Model/BillingVat.php',
	'ZPOS\\Model\\Cart' => $baseDir . '/includes/Model/Cart.php',
	'ZPOS\\Model\\Gateway' => $baseDir . '/includes/Model/Gateway.php',
	'ZPOS\\Model\\Product' => $baseDir . '/includes/Model/Product.php',
	'ZPOS\\Model\\SplitOrder' => $baseDir . '/includes/Model/SplitOrder.php',
	'ZPOS\\Model\\SplitPayment' => $baseDir . '/includes/Model/SplitPayment.php',
	'ZPOS\\Model\\VatControl' => $baseDir . '/includes/Model/VatControl.php',
	'ZPOS\\Plugin' => $baseDir . '/includes/Plugin.php',
	'ZPOS\\Setup' => $baseDir . '/includes/Setup.php',
	'ZPOS\\Station' => $baseDir . '/includes/Station.php',
	'ZPOS\\StationException' => $baseDir . '/includes/StationException.php',
	'ZPOS\\Structure\\AddDefaultImage' => $baseDir . '/includes/Structure/AddDefaultImage.php',
	'ZPOS\\Structure\\ArrayObject' => $baseDir . '/includes/Structure/ArrayObject.php',
	'ZPOS\\Structure\\EmptyEntity' => $baseDir . '/includes/Structure/EmptyEntity.php',
	'ZPOS\\Structure\\ProductIds' => $baseDir . '/includes/Structure/ProductIds.php',
	'ZPOS\\Structure\\ProductResponse' => $baseDir . '/includes/Structure/ProductResponse.php',
	'ZPOS\\Translation' => $baseDir . '/includes/Translation.php',
	'ZPOS\\Woocommerce' => $baseDir . '/includes/Woocommerce.php',
	'ZPOS\\Woocommerce\\Account' => $baseDir . '/includes/Woocommerce/Account.php',
];
