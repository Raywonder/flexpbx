const logger = require('../utils/logger');

async function initializeAsterisk() {
    // Stub Asterisk initialization
    logger.info('Asterisk service initialized (stub)');
    return Promise.resolve();
}

module.exports = {
    initializeAsterisk
};