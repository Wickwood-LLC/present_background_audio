window.RevealBackgroundAudio = window.RevealBackgroundAudio || {
    id: 'RevealBackgroundAudio',
    playing: false,
    backup_config: {},
    deck: null,
    current_audio: null,
    init: function(deck) {
        this.deck = deck;
        let reveal_element = deck.getRevealElement();
        let plugin = this;
        // Get all start buttons
        const bg_start_buttons = reveal_element.querySelectorAll('[data-bg-audio-start-button]');

        // Loop through the buttons and attach click event listener
        bg_start_buttons.forEach(child => {
            child.classList.add('background-audio-start-button');
            child.addEventListener('click', function (event){
                plugin.startAudio(this);
            });
        });
        deck.on('slidechanged', (event) => {
            if (plugin.current_audio) {
                plugin.current_audio.pause();
            }
        });
        deck.on('slidetransitionend', (event) => {
            if (plugin.current_audio) {
                plugin.current_audio.play();
            }
        });
    },
    backupConfigs: function(configs_items) {
        const plugin = this;
        let config = plugin.deck.getConfig();
        configs_items.forEach((property, index) => {
            plugin.backup_config[property] = config[property];
        });
    },
    restoreConfigs: function(configs_items) {
        const plugin = this;
        let config = {};
        configs_items.forEach((property, index) => {
            config[property] = plugin.backup_config[property];
        });
        plugin.deck.configure(config);
    },
    startAudio: function(button) {
        const plugin = this;
        let config = plugin.deck.getConfig();
        let audio_source;
        if (button.hasAttribute('data-bg-audio-src')) {
            audio_source = button.getAttribute('data-bg-audio-src');
        }
        else if ('background_audio' in config) {
            audio_source = config.background_audio;
        }
        if (audio_source) {
            let audio = new Audio(audio_source);
            if (audio) {
                plugin.backupConfigs(['autoSlide', 'autoSlideStoppable', 'controls'], config);
                plugin.deck.configure({autoSlide: 0, autoSlideStoppable: false, controls: false});
                audio.play();
                plugin.current_audio = audio;
                audio.addEventListener("ended", (event) => {
                    plugin.stopAudio()
                });
            }
        }
    },
    stopAudio: function() {
        this.current_audio = null;
        this.restoreConfigs(['autoSlide', 'autoSlideStoppable', 'controls']);
    }

};