// import RegionsPlugin from 'https://unpkg.com/wavesurfer.js@7.9.1/dist/plugins/regions.min.js';

(function(Drupal){
  Drupal.behaviors.audio_guide = {
    attach: function(context, settings) {
      once('wavesurfer', '.audio-guide-track').forEach(function (element) {
        let config = JSON.parse(element.getAttribute('data-configs'));
        config.container = element;
        // Initialize the Regions plugin
        const regions_plugin = WaveSurfer.Regions.create();
        const timeline = WaveSurfer.Timeline.create();
        // Add the plugin to the WaveSurfer instance
        config.plugins = [
          regions_plugin,
          timeline,
        ];
        // Create a WaveSurfer instance
        const ws = WaveSurfer.create(config);

        // Play on click
        ws.on('interaction', () => {
          ws.play()
        });

        // Give regions a random color when they are created
        const random = (min, max) => Math.random() * (max - min) + min
        const randomColor = () => `rgba(${random(0, 255)}, ${random(0, 255)}, ${random(0, 255)}, 0.5)`


        let region_configs = JSON.parse(element.getAttribute('data-regions'));
        // Create a region
        let regions = [];
        ws.on('decode', function (duration) {
          region_configs.forEach((region_config) => {
            if (!('color' in region_config)) {
              region_config.color = randomColor();
            }
            let region = regions_plugin.addRegion(region_config);
            region.type = region_config.type;
            regions.push(region);
          });
        });

        ws.on('error', e => alert(e))

        regions_plugin.on('region-clicked', (region, e) => {
          e.stopPropagation() // prevent triggering a click on the waveform
          // activeRegion = region
          region.play(true)
          // if (region.type !== 'transition') {
          //   region.setOptions({ color: randomColor() })
          // }
        })

        // regions_plugin.enableDragSelection({
        //   color: 'rgba(255, 0, 0, 0.1)',
        // })
      });
    }
  }
})(Drupal, WaveSurfer, once);