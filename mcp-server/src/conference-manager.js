/**
 * Conference Bridge Manager
 * Implements conference management patterns inspired by FlexPBX
 */

export class ConferenceManager {
  constructor(amiClient) {
    this.ami = amiClient;
  }

  /**
   * List all active conferences
   */
  async listConferences() {
    const response = await this.ami.command('confbridge list');
    return this.parseConferenceList(response.Output || '');
  }

  /**
   * Parse conference list output
   */
  parseConferenceList(output) {
    const conferences = [];
    const lines = output.split('\n');

    for (const line of lines) {
      const match = line.match(/^(\S+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)/);
      if (match) {
        conferences.push({
          conference: match[1],
          participants: parseInt(match[2]),
          marked: parseInt(match[3]),
          locked: match[4] === 'locked',
          muted: match[5] === 'muted'
        });
      }
    }

    return conferences;
  }

  /**
   * Get participants in a conference
   */
  async getParticipants(conference) {
    const response = await this.ami.command(`confbridge list ${conference}`);
    return this.parseParticipantList(response.Output || '');
  }

  /**
   * Parse participant list output
   */
  parseParticipantList(output) {
    const participants = [];
    const lines = output.split('\n');

    for (const line of lines) {
      const match = line.match(/^(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)/);
      if (match && match[1] !== 'Channel') {
        participants.push({
          channel: match[1],
          userProfile: match[2],
          bridgeProfile: match[3],
          menu: match[4],
          callerId: match[5].trim()
        });
      }
    }

    return participants;
  }

  /**
   * Kick participant from conference
   */
  async kickParticipant(conference, channel) {
    const response = await this.ami.command(`confbridge kick ${conference} ${channel}`);
    return {
      success: response.Output?.includes('kicked') || false,
      message: response.Output || ''
    };
  }

  /**
   * Mute participant
   */
  async muteParticipant(conference, channel) {
    const response = await this.ami.command(`confbridge mute ${conference} ${channel}`);
    return {
      success: response.Output?.includes('muted') || false,
      message: response.Output || ''
    };
  }

  /**
   * Unmute participant
   */
  async unmuteParticipant(conference, channel) {
    const response = await this.ami.command(`confbridge unmute ${conference} ${channel}`);
    return {
      success: response.Output?.includes('unmuted') || false,
      message: response.Output || ''
    };
  }

  /**
   * Lock conference
   */
  async lockConference(conference) {
    const response = await this.ami.command(`confbridge lock ${conference}`);
    return {
      success: response.Output?.includes('locked') || false,
      message: response.Output || ''
    };
  }

  /**
   * Unlock conference
   */
  async unlockConference(conference) {
    const response = await this.ami.command(`confbridge unlock ${conference}`);
    return {
      success: response.Output?.includes('unlocked') || false,
      message: response.Output || ''
    };
  }

  /**
   * Get conference statistics
   */
  async getConferenceStats(conference) {
    const participants = await this.getParticipants(conference);
    const conferences = await this.listConferences();
    const conferenceInfo = conferences.find(c => c.conference === conference);

    return {
      conference,
      exists: !!conferenceInfo,
      participants: participants.length,
      details: conferenceInfo || null,
      participantList: participants
    };
  }
}
