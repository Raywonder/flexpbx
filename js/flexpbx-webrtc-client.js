/**
 * FlexPBX WebRTC Client Library
 * Browser-based audio calls with mono/stereo support
 *
 * Usage:
 *   const client = new FlexPBXWebRTCClient();
 *   await client.connect(extension, token);
 *   await client.makeCall('2001', 'mono'); // or 'stereo'
 */

class FlexPBXWebRTCClient {
    constructor(serverUrl = null) {
        this.serverUrl = serverUrl || this.getDefaultServerUrl();
        this.ws = null;
        this.peerConnection = null;
        this.localStream = null;
        this.remoteStream = null;
        this.extension = null;
        this.authenticated = false;
        this.currentCall = null;
        this.audioMode = 'mono'; // 'mono' or 'stereo'
        this.muted = false;

        // Event handlers
        this.onConnected = null;
        this.onAuthenticated = null;
        this.onCallInitiated = null;
        this.onCallReceived = null;
        this.onCallAnswered = null;
        this.onCallEnded = null;
        this.onCallHeld = null;
        this.onCallUnheld = null;
        this.onError = null;
        this.onRemoteStream = null;

        // ICE servers configuration
        this.iceServers = [
            { urls: 'stun:stun.devinecreations.net:3478' },
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ];
    }

    /**
     * Get default WebSocket server URL
     */
    getDefaultServerUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8088; // Default WebRTC server port
        return `${protocol}//${host}:${port}/ws`;
    }

    /**
     * Connect to WebRTC server
     */
    async connect(extension, token) {
        return new Promise((resolve, reject) => {
            this.extension = extension;

            try {
                this.ws = new WebSocket(this.serverUrl);

                this.ws.onopen = () => {
                    console.log('✓ Connected to FlexPBX WebRTC Server');
                };

                this.ws.onmessage = async (event) => {
                    const data = JSON.parse(event.data);
                    await this.handleMessage(data);
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    if (this.onError) {
                        this.onError(error);
                    }
                    reject(error);
                };

                this.ws.onclose = () => {
                    console.log('✗ Disconnected from FlexPBX WebRTC Server');
                    this.authenticated = false;
                };

                // Wait for connected message, then authenticate
                const messageHandler = async (event) => {
                    const data = JSON.parse(event.data);
                    if (data.type === 'connected') {
                        // Send authentication
                        this.send({
                            type: 'authenticate',
                            extension: extension,
                            token: token
                        });

                        // Wait for authentication response
                        const authHandler = async (event) => {
                            const authData = JSON.parse(event.data);
                            if (authData.type === 'authenticated') {
                                this.authenticated = true;
                                if (this.onAuthenticated) {
                                    this.onAuthenticated(authData);
                                }
                                resolve(authData);
                            } else if (authData.type === 'error') {
                                reject(new Error(authData.error));
                            }
                            this.ws.removeEventListener('message', authHandler);
                        };

                        this.ws.addEventListener('message', authHandler);
                        this.ws.removeEventListener('message', messageHandler);
                    }
                };

                this.ws.addEventListener('message', messageHandler);

            } catch (error) {
                reject(error);
            }
        });
    }

    /**
     * Handle WebSocket messages
     */
    async handleMessage(data) {
        const { type } = data;

        switch (type) {
            case 'connected':
                if (this.onConnected) {
                    this.onConnected(data);
                }
                break;

            case 'authenticated':
                // Handled in connect()
                break;

            case 'offer':
                await this.handleOffer(data);
                break;

            case 'answer':
                await this.handleAnswer(data);
                break;

            case 'ice_candidate':
                await this.handleICECandidate(data);
                break;

            case 'call_initiated':
                this.currentCall = {
                    uniqueid: data.uniqueid,
                    destination: data.destination,
                    audioMode: data.audioMode
                };
                if (this.onCallInitiated) {
                    this.onCallInitiated(data);
                }
                break;

            case 'call_event':
                this.handleCallEvent(data);
                break;

            case 'call_held':
                if (this.onCallHeld) {
                    this.onCallHeld(data);
                }
                break;

            case 'call_unheld':
                if (this.onCallUnheld) {
                    this.onCallUnheld(data);
                }
                break;

            case 'error':
                console.error('Server error:', data.error);
                if (this.onError) {
                    this.onError(data.error);
                }
                break;

            default:
                console.log('Unknown message type:', type);
        }
    }

    /**
     * Handle call events
     */
    handleCallEvent(data) {
        const { event } = data;

        switch (event) {
            case 'hangup':
                this.currentCall = null;
                this.closePeerConnection();
                if (this.onCallEnded) {
                    this.onCallEnded(data);
                }
                break;

            case 'hold':
                if (this.onCallHeld) {
                    this.onCallHeld(data);
                }
                break;

            case 'unhold':
                if (this.onCallUnheld) {
                    this.onCallUnheld(data);
                }
                break;

            default:
                console.log('Call event:', event);
        }
    }

    /**
     * Make a call
     */
    async makeCall(destination, audioMode = 'mono') {
        if (!this.authenticated) {
            throw new Error('Not authenticated');
        }

        this.audioMode = audioMode;

        // Get local audio stream
        await this.getLocalStream();

        // Create peer connection
        await this.createPeerConnection();

        // Create offer
        const offer = await this.peerConnection.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: false
        });

        await this.peerConnection.setLocalDescription(offer);

        // Send offer to server
        this.send({
            type: 'make_call',
            destination: destination,
            audioMode: audioMode
        });

        // Also send WebRTC offer for peer-to-peer audio
        this.send({
            type: 'offer',
            recipientExtension: destination,
            offer: offer
        });

        return this.currentCall;
    }

    /**
     * Handle incoming offer
     */
    async handleOffer(data) {
        const { from, offer } = data;

        // Create peer connection
        await this.createPeerConnection();

        // Set remote description
        await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

        // Create answer
        const answer = await this.peerConnection.createAnswer();
        await this.peerConnection.setLocalDescription(answer);

        // Send answer back
        this.send({
            type: 'answer',
            recipientExtension: from,
            answer: answer
        });

        if (this.onCallReceived) {
            this.onCallReceived({ from: from });
        }
    }

    /**
     * Handle incoming answer
     */
    async handleAnswer(data) {
        const { from, answer } = data;

        if (this.peerConnection) {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(answer));

            if (this.onCallAnswered) {
                this.onCallAnswered({ from: from });
            }
        }
    }

    /**
     * Handle ICE candidate
     */
    async handleICECandidate(data) {
        const { from, candidate } = data;

        if (this.peerConnection && candidate) {
            try {
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                console.error('Error adding ICE candidate:', error);
            }
        }
    }

    /**
     * Get local audio stream
     */
    async getLocalStream() {
        if (this.localStream) {
            return this.localStream;
        }

        const constraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
                channelCount: this.audioMode === 'stereo' ? 2 : 1
            },
            video: false
        };

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            console.log(`✓ Got local ${this.audioMode} audio stream`);
            return this.localStream;
        } catch (error) {
            console.error('Error getting local stream:', error);
            throw error;
        }
    }

    /**
     * Create WebRTC peer connection
     */
    async createPeerConnection() {
        if (this.peerConnection) {
            return this.peerConnection;
        }

        const config = {
            iceServers: this.iceServers
        };

        this.peerConnection = new RTCPeerConnection(config);

        // Add local stream
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
        }

        // Handle ICE candidates
        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.send({
                    type: 'ice_candidate',
                    recipientExtension: this.currentCall ? this.currentCall.destination : null,
                    candidate: event.candidate
                });
            }
        };

        // Handle remote stream
        this.peerConnection.ontrack = (event) => {
            console.log('✓ Received remote track');
            this.remoteStream = event.streams[0];

            if (this.onRemoteStream) {
                this.onRemoteStream(this.remoteStream);
            }
        };

        // Handle connection state changes
        this.peerConnection.onconnectionstatechange = () => {
            console.log('Connection state:', this.peerConnection.connectionState);
        };

        // Handle ICE connection state
        this.peerConnection.oniceconnectionstatechange = () => {
            console.log('ICE connection state:', this.peerConnection.iceConnectionState);
        };

        return this.peerConnection;
    }

    /**
     * Hangup current call
     */
    hangup() {
        if (!this.currentCall) {
            return;
        }

        this.send({
            type: 'hangup',
            uniqueid: this.currentCall.uniqueid
        });

        this.closePeerConnection();
        this.currentCall = null;
    }

    /**
     * Hold current call
     */
    hold() {
        if (!this.currentCall) {
            return;
        }

        this.send({
            type: 'hold_call',
            uniqueid: this.currentCall.uniqueid
        });
    }

    /**
     * Unhold current call
     */
    unhold() {
        if (!this.currentCall) {
            return;
        }

        this.send({
            type: 'unhold_call',
            uniqueid: this.currentCall.uniqueid
        });
    }

    /**
     * Transfer current call
     */
    transfer(destination) {
        if (!this.currentCall) {
            return;
        }

        this.send({
            type: 'transfer_call',
            uniqueid: this.currentCall.uniqueid,
            destination: destination
        });
    }

    /**
     * Send DTMF tones
     */
    sendDTMF(digits) {
        if (!this.currentCall) {
            return;
        }

        this.send({
            type: 'send_dtmf',
            uniqueid: this.currentCall.uniqueid,
            digits: digits
        });
    }

    /**
     * Mute microphone
     */
    mute() {
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = false;
            });
            this.muted = true;

            if (this.currentCall) {
                this.send({
                    type: 'mute',
                    uniqueid: this.currentCall.uniqueid
                });
            }
        }
    }

    /**
     * Unmute microphone
     */
    unmute() {
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = true;
            });
            this.muted = false;

            if (this.currentCall) {
                this.send({
                    type: 'unmute',
                    uniqueid: this.currentCall.uniqueid
                });
            }
        }
    }

    /**
     * Set audio mode (mono/stereo)
     */
    async setAudioMode(mode) {
        if (mode !== 'mono' && mode !== 'stereo') {
            throw new Error('Invalid audio mode. Must be "mono" or "stereo"');
        }

        this.audioMode = mode;

        // If in call, recreate stream
        if (this.localStream) {
            this.stopLocalStream();
            await this.getLocalStream();

            // Update peer connection
            if (this.peerConnection) {
                const sender = this.peerConnection.getSenders().find(s => s.track?.kind === 'audio');
                if (sender) {
                    const track = this.localStream.getAudioTracks()[0];
                    await sender.replaceTrack(track);
                }
            }
        }

        if (this.currentCall) {
            this.send({
                type: 'set_audio_mode',
                uniqueid: this.currentCall.uniqueid,
                mode: mode
            });
        }

        console.log(`✓ Audio mode set to ${mode}`);
    }

    /**
     * Stop local stream
     */
    stopLocalStream() {
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
    }

    /**
     * Close peer connection
     */
    closePeerConnection() {
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }

        this.stopLocalStream();
        this.remoteStream = null;
    }

    /**
     * Send message to server
     */
    send(message) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
        } else {
            console.error('WebSocket not connected');
        }
    }

    /**
     * Disconnect from server
     */
    disconnect() {
        if (this.currentCall) {
            this.hangup();
        }

        this.closePeerConnection();

        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }

        this.authenticated = false;
    }
}

// Export for use in browser
if (typeof window !== 'undefined') {
    window.FlexPBXWebRTCClient = FlexPBXWebRTCClient;
}
