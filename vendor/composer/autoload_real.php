<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit8a36f8fbb4a0132f0523b51e262baada
{
	private static $loader;

	public static function loadClassLoader($class)
	{
		if ('Composer\Autoload\ClassLoader' === $class) {
			require __DIR__ . '/ClassLoader.php';
		}
	}

	/**
	 * @return \Composer\Autoload\ClassLoader
	 */
	public static function getLoader()
	{
		if (null !== self::$loader) {
			return self::$loader;
		}

		spl_autoload_register(
			['ComposerAutoloaderInit8a36f8fbb4a0132f0523b51e262baada', 'loadClassLoader'],
			true,
			true
		);
		self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
		spl_autoload_unregister([
			'ComposerAutoloaderInit8a36f8fbb4a0132f0523b51e262baada',
			'loadClassLoader',
		]);

		require __DIR__ . '/autoload_static.php';
		call_user_func(
			\Composer\Autoload\ComposerStaticInit8a36f8fbb4a0132f0523b51e262baada::getInitializer($loader)
		);

		$loader->register(true);

		return $loader;
	}
}
