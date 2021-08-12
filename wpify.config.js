module.exports = {
	wordPressUrl: 'https://www.wpify-woo.test',
	config: {
		build: 'build',
		entry: {
			'settings': [
				'./assets/admin/settings.js',
				'./assets/admin/settings.scss',
			]
		},
	},
};
