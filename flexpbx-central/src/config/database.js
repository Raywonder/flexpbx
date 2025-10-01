const { Sequelize } = require('sequelize');
const logger = require('../utils/logger');

let sequelize;

const connectDatabase = async () => {
  const dbType = process.env.DB_TYPE || 'sqlite';
  const dbName = process.env.DB_NAME || 'accessible_pbx';

  try {
    switch (dbType.toLowerCase()) {
      case 'mysql':
      case 'mariadb':
        sequelize = new Sequelize(dbName, process.env.DB_USER, process.env.DB_PASSWORD, {
          host: process.env.DB_HOST || 'localhost',
          port: process.env.DB_PORT || 3306,
          dialect: dbType.toLowerCase() === 'mariadb' ? 'mariadb' : 'mysql',
          logging: process.env.NODE_ENV === 'development' ? console.log : false,
          pool: {
            max: 10,
            min: 0,
            acquire: 30000,
            idle: 10000
          }
        });
        break;

      case 'postgres':
        sequelize = new Sequelize(dbName, process.env.DB_USER, process.env.DB_PASSWORD, {
          host: process.env.DB_HOST || 'localhost',
          port: process.env.DB_PORT || 5432,
          dialect: 'postgres',
          logging: process.env.NODE_ENV === 'development' ? console.log : false,
          pool: {
            max: 10,
            min: 0,
            acquire: 30000,
            idle: 10000
          }
        });
        break;

      case 'sqlite':
      default:
        const path = require('path');
        const dbPath = process.env.SQLITE_PATH || path.join(__dirname, '../../data/accessible_pbx.sqlite');
        sequelize = new Sequelize({
          dialect: 'sqlite',
          storage: dbPath,
          logging: process.env.NODE_ENV === 'development' ? console.log : false
        });
        break;
    }

    await sequelize.authenticate();
    logger.info(`Database connected successfully (${dbType})`);

    await initializeModels();
    await sequelize.sync({ alter: process.env.NODE_ENV === 'development' });

    return sequelize;
  } catch (error) {
    logger.error('Database connection error:', error);
    throw error;
  }
};

const initializeModels = async () => {
  const models = require('../models');
  Object.keys(models).forEach(modelName => {
    if (models[modelName].associate) {
      models[modelName].associate(models);
    }
  });
};

const getSequelize = () => sequelize;

module.exports = {
  connectDatabase,
  getSequelize,
  sequelize
};