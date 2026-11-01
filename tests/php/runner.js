/**
 * PHP Lint and Domain Verification Test Runner
 *
 * Runs 0-dependency static analysis and domain verification over all plugin PHP files.
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const ROOT_DIR = path.resolve(__dirname, '../..');
const TEXT_DOMAIN = 'stateless-wa-commerce';

function getAllPhpFiles(dir, fileList = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
        if (entry.name.startsWith('.') || entry.name === 'node_modules') {
            continue;
        }
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            getAllPhpFiles(fullPath, fileList);
        } else if (entry.isFile() && entry.name.endsWith('.php')) {
            fileList.push(fullPath);
        }
    }
    return fileList;
}

function runPhpVerification() {
    console.log('\n--- Running PHP Domain, Invariant & Lint Verification ---');
    const phpFiles = getAllPhpFiles(ROOT_DIR);
    console.log(`Found ${phpFiles.length} PHP files to inspect.`);

    let errors = [];

    // 1. Version Synchronization Check (4-way)
    const pkgJson = JSON.parse(fs.readFileSync(path.join(ROOT_DIR, 'package.json'), 'utf8'));
    const targetVersion = pkgJson.version;

    const mainPluginContent = fs.readFileSync(path.join(ROOT_DIR, 'stateless-wa-commerce.php'), 'utf8');
    const versionHeaderMatch = mainPluginContent.match(/\*\s*Version:\s*([0-9\.]+)/);
    if (!versionHeaderMatch || versionHeaderMatch[1] !== targetVersion) {
        errors.push(`Version mismatch in stateless-wa-commerce.php: expected ${targetVersion}, found ${versionHeaderMatch ? versionHeaderMatch[1] : 'none'}`);
    }

    const configContent = fs.readFileSync(path.join(ROOT_DIR, 'core/config.php'), 'utf8');
    const swacVersionMatch = configContent.match(/define\('SWAC_VERSION',\s*'([^']+)'\)/);
    if (!swacVersionMatch || swacVersionMatch[1] !== targetVersion) {
        errors.push(`Version mismatch in core/config.php SWAC_VERSION: expected ${targetVersion}, found ${swacVersionMatch ? swacVersionMatch[1] : 'none'}`);
    }

    const changelogContent = fs.readFileSync(path.join(ROOT_DIR, 'CHANGELOG.md'), 'utf8');
    const changelogLatestMatch = changelogContent.match(/^## \[([0-9\.]+)\]\s*—\s*(\d{2}-\d{2}-\d{4})/m);
    if (!changelogLatestMatch || changelogLatestMatch[1] !== targetVersion) {
        errors.push(`Version mismatch in CHANGELOG.md top release: expected ${targetVersion}, found ${changelogLatestMatch ? changelogLatestMatch[1] : 'none'}`);
    }

    // Verify all release headers in CHANGELOG.md follow DD-MM-YYYY
    const releaseHeaderRegex = /^## \[([^\]]+)\]\s*—\s*(.+)$/gm;
    let headerMatch;
    while ((headerMatch = releaseHeaderRegex.exec(changelogContent)) !== null) {
        const dateStr = headerMatch[2].trim();
        if (!/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
            errors.push(`CHANGELOG.md release [${headerMatch[1]}] date '${dateStr}' violates Indian standard format DD-MM-YYYY`);
        }
    }

    // 2. Check HPOS Compatibility Declaration
    if (!mainPluginContent.includes("'custom_order_tables'") || !mainPluginContent.includes("'cart_checkout_blocks'")) {
        errors.push('HPOS (custom_order_tables) or cart_checkout_blocks compatibility declaration missing from root plugin file');
    }

    // 3. Inspect Each PHP File
    const i18n2ArgRegex = /(?:__|__e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*(['"][^'"]*['"])\s*,\s*(['"][^'"]*['"])/g;
    const i18n3ArgRegex = /(?:_x|esc_html_x|esc_attr_x)\s*\(\s*(['"][^'"]*['"])\s*,\s*(['"][^'"]*['"])\s*,\s*(['"][^'"]*['"])/g;
    const i18n4ArgRegex = /(?:_n|_nx)\s*\(\s*(['"][^'"]*['"])\s*,\s*(['"][^'"]*['"])\s*,\s*[^,]+\s*,\s*(['"][^'"]*['"])/g;
    const sessionRegex = /\bsession_start\s*\(/i;
    const rawCookieRegex = /\bsetcookie\s*\(/i;
    const sessionSuperglobalRegex = /\$_SESSION\b/;

    for (const filePath of phpFiles) {
        const relativePath = path.relative(ROOT_DIR, filePath).replace(/\\/g, '/');
        const content = fs.readFileSync(filePath, 'utf8');

        // Check for session_start invariant
        if (sessionRegex.test(content)) {
            errors.push(`[${relativePath}] Forbidden session_start() call detected`);
        }

        // Check for direct setcookie invariant
        if (rawCookieRegex.test(content)) {
            errors.push(`[${relativePath}] Forbidden raw setcookie() call detected`);
        }

        // Check for $_SESSION invariant
        if (sessionSuperglobalRegex.test(content)) {
            errors.push(`[${relativePath}] Forbidden $_SESSION superglobal detected`);
        }

        // Check text domains (2-arg)
        let match;
        const scanner2 = new RegExp(i18n2ArgRegex.source, 'g');
        while ((match = scanner2.exec(content)) !== null) {
            const rawDomain = match[2].replace(/['"]/g, '');
            if (rawDomain !== TEXT_DOMAIN) {
                errors.push(`[${relativePath}] Invalid text domain '${rawDomain}'. Expected '${TEXT_DOMAIN}'`);
            }
        }

        // Check text domains (3-arg)
        const scanner3 = new RegExp(i18n3ArgRegex.source, 'g');
        while ((match = scanner3.exec(content)) !== null) {
            const rawDomain = match[3].replace(/['"]/g, '');
            if (rawDomain !== TEXT_DOMAIN) {
                errors.push(`[${relativePath}] Invalid text domain '${rawDomain}'. Expected '${TEXT_DOMAIN}'`);
            }
        }

        // Check text domains (4-arg)
        const scanner4 = new RegExp(i18n4ArgRegex.source, 'g');
        while ((match = scanner4.exec(content)) !== null) {
            const rawDomain = match[3].replace(/['"]/g, '');
            if (rawDomain !== TEXT_DOMAIN) {
                errors.push(`[${relativePath}] Invalid text domain '${rawDomain}'. Expected '${TEXT_DOMAIN}'`);
            }
        }

        // Basic structural bracket matching
        let braceCount = 0;
        let inString = false;
        let stringChar = '';
        for (let i = 0; i < content.length; i++) {
            const char = content[i];
            const prev = i > 0 ? content[i - 1] : '';

            if ((char === "'" || char === '"') && prev !== '\\') {
                if (!inString) {
                    inString = true;
                    stringChar = char;
                } else if (stringChar === char) {
                    inString = false;
                }
            } else if (!inString) {
                if (char === '{') braceCount++;
                if (char === '}') braceCount--;
            }
        }
        if (braceCount !== 0) {
            errors.push(`[${relativePath}] Unbalanced braces detected (net offset: ${braceCount})`);
        }
    }

    // 4. Optional php -l if PHP binary is installed
    let phpBin = 'php';
    if (fs.existsSync('K:\\xampp\\php\\php.exe')) {
        phpBin = 'K:\\xampp\\php\\php.exe';
    } else if (process.env.PHP_BINARY && fs.existsSync(process.env.PHP_BINARY)) {
        phpBin = process.env.PHP_BINARY;
    }

    const hasPhpCli = spawnSync(phpBin, ['-v'], { encoding: 'utf8' }).status === 0;
    if (hasPhpCli) {
        console.log(`PHP CLI detected (${phpBin}). Running syntax lint (php -l)...\n`);
        for (const filePath of phpFiles) {
            const res = spawnSync(phpBin, ['-l', filePath], { encoding: 'utf8' });
            if (res.status !== 0) {
                errors.push(`PHP Syntax Error in ${path.relative(ROOT_DIR, filePath)}: ${res.stderr || res.stdout}`);
            }
        }
    } else {
        console.log('Note: PHP CLI not installed in current environment. Static syntax and invariant checks verified.');
    }

    // Results
    if (errors.length > 0) {
        console.error(`\n❌ PHP Verification Failed with ${errors.length} error(s):`);
        errors.forEach(err => console.error(`  - ${err}`));
        process.exit(1);
    } else {
        console.log(`\n✅ PHP Domain, Invariant & Lint Verification Passed (${phpFiles.length} files verified)!`);
    }
}

runPhpVerification();
