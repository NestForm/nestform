/**
 * Concatenate CSS partials and minify shipped bundles.
 *
 * Usage (from plugin root):
 *   npm run build:css
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import CleanCSS from 'clean-css';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const minifier = new CleanCSS({
	level: 2,
	format: 'keep-breaks',
});

function readSortedCss(dir) {
	return fs
		.readdirSync(dir)
		.filter((name) => name.endsWith('.css'))
		.sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
}

function formatKb(bytes) {
	return `${(bytes / 1024).toFixed(1)} KB`;
}

function writeMinifiedBundle(target, header, body, label) {
	const raw = header + body.trimEnd() + '\n';
	const result = minifier.minify(raw);

	if (result.errors.length) {
		console.error(`Minify failed (${label}):`, result.errors.join('; '));
		process.exit(1);
	}

	fs.writeFileSync(target, result.styles);
	const saved = raw.length - result.styles.length;
	const pct = raw.length ? Math.round((saved / raw.length) * 100) : 0;
	console.log(
		`Wrote ${target} (${label}: ${formatKb(raw.length)} → ${formatKb(result.styles.length)}, −${pct}%)`
	);
}

function bundleAdmin() {
	const adminDir = path.join(__dirname, 'admin');
	const files = readSortedCss(adminDir);
	const header =
		'/**\n' +
		' * Nestform admin — bundled + minified from assets/css/admin/*.css\n' +
		' * Do not edit directly. Run: npm run build:css\n' +
		' */\n';

	let body = '';
	for (const file of files) {
		body += `/* ${file} */\n`;
		body += fs.readFileSync(path.join(adminDir, file), 'utf8').trimEnd();
		body += '\n\n';
	}

	writeMinifiedBundle(path.join(__dirname, 'admin.css'), header, body, `${files.length} partials`);
}

function bundleFront() {
	const frontFile = path.join(__dirname, 'front', 'forms.css');
	if (!fs.existsSync(frontFile)) {
		console.warn('Skip front: assets/css/front/forms.css missing');
		return;
	}

	const header =
		'/**\n' +
		' * Nestform front — bundled + minified from assets/css/front/forms.css\n' +
		' * Do not edit directly. Run: npm run build:css\n' +
		' */\n';

	writeMinifiedBundle(
		path.join(__dirname, 'front.css'),
		header,
		fs.readFileSync(frontFile, 'utf8'),
		'front'
	);
}

bundleAdmin();
bundleFront();
