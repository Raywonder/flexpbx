const { notarize } = require('electron-notarize');

exports.default = async function notarizing(context) {
    const { electronPlatformName, appOutDir } = context;

    if (electronPlatformName !== 'darwin') {
        return;
    }

    const appName = context.packager.appInfo.productFilename;
    const appPath = `${appOutDir}/${appName}.app`;

    const { APPLE_ID, APPLE_ID_PASSWORD, APPLE_TEAM_ID } = process.env;

    if (!APPLE_ID || !APPLE_ID_PASSWORD) {
        console.log('⚠️ Skipping notarization: APPLE_ID and APPLE_ID_PASSWORD not set');
        return;
    }

    console.log('🔐 Starting notarization process...');

    try {
        await notarize({
            appBundleId: 'com.flexpbx.desktop',
            appPath,
            appleId: APPLE_ID,
            appleIdPassword: APPLE_ID_PASSWORD,
            teamId: APPLE_TEAM_ID
        });

        console.log('✅ Notarization completed successfully');
    } catch (error) {
        console.error('❌ Notarization failed:', error);
        throw error;
    }
};