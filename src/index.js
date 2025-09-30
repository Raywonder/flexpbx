const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const compression = require('compression');
const session = require('express-session');
const rateLimit = require('express-rate-limit');
const path = require('path');
const http = require('http');
const socketIO = require('socket.io');
require('dotenv').config();

const { connectDatabase } = require('./config/database');
const { initializeRedis } = require('./config/redis');
const { initializeAsterisk } = require('./services/asterisk');
const logger = require('./utils/logger');
const apiRoutes = require('./routes/api');
const authRoutes = require('./routes/auth');
const accessibilityRoutes = require('./routes/accessibility');
const setupRoutes = require('./routes/setup');
const errorHandler = require('./middleware/errorHandler');

const app = express();
const server = http.createServer(app);
const io = socketIO(server, {
  cors: {
    origin: process.env.FRONTEND_URL || '*',
    methods: ['GET', 'POST']
  }
});

const PORT = process.env.PORT || 3000;

const limiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
  message: 'Too many requests from this IP, please try again later.'
});

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", "data:", "https:"],
      connectSrc: ["'self'", "wss:", "ws:"],
      fontSrc: ["'self'"],
      objectSrc: ["'none'"],
      mediaSrc: ["'self'"],
      frameSrc: ["'none'"],
    },
  },
}));

app.use(cors());
app.use(compression());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '../public')));

app.use(session({
  secret: process.env.SESSION_SECRET || 'accessible-pbx-secret',
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: process.env.NODE_ENV === 'production',
    httpOnly: true,
    maxAge: 24 * 60 * 60 * 1000
  }
}));

app.use('/api', limiter);
app.use('/api/v1/auth', authRoutes);
app.use('/api/v1', apiRoutes);
app.use('/api/v1/accessibility', accessibilityRoutes);
app.use('/api/v1/setup', setupRoutes);

app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
    accessibility: {
      screenReaderSupport: process.env.SCREEN_READER_SUPPORT === 'true',
      audioFeedback: process.env.AUDIO_FEEDBACK_ENABLED === 'true',
      voiceAnnouncements: process.env.VOICE_ANNOUNCEMENTS_ENABLED === 'true'
    }
  });
});

app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, '../public/index.html'));
});

app.get('/softphone', (req, res) => {
  res.sendFile(path.join(__dirname, '../public/softphone.html'));
});

app.get('/admin', (req, res) => {
  res.sendFile(path.join(__dirname, '../public/admin.html'));
});

app.get('/setup', (req, res) => {
  res.sendFile(path.join(__dirname, '../public/server-setup.html'));
});

app.use(errorHandler);

io.on('connection', (socket) => {
  logger.info('New WebSocket connection established');

  socket.on('register', (data) => {
    socket.join(data.extension);
    socket.emit('registered', { extension: data.extension });
  });

  socket.on('call-event', (data) => {
    socket.to(data.to).emit('incoming-call', data);
  });

  socket.on('accessibility-request', (data) => {
    socket.emit('accessibility-response', {
      feature: data.feature,
      enabled: process.env[`${data.feature.toUpperCase()}_ENABLED`] === 'true'
    });
  });

  socket.on('disconnect', () => {
    logger.info('WebSocket connection closed');
  });
});

async function startServer() {
  try {
    await connectDatabase();
    logger.info('Database connected successfully');

    await initializeRedis();
    logger.info('Redis connected successfully');

    await initializeAsterisk();
    logger.info('Asterisk AMI connected successfully');

    server.listen(PORT, '0.0.0.0', () => {
      logger.info(`Accessible PBX Server running on port ${PORT}`);
      logger.info(`Accessibility features enabled: ${process.env.ACCESSIBILITY_ENABLED === 'true'}`);
    });
  } catch (error) {
    logger.error('Failed to start server:', error);
    process.exit(1);
  }
}

process.on('SIGTERM', async () => {
  logger.info('SIGTERM received, shutting down gracefully');
  server.close(() => {
    logger.info('Server closed');
    process.exit(0);
  });
});

process.on('unhandledRejection', (reason, promise) => {
  logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

startServer();

module.exports = { app, io };