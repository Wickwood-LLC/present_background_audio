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
        const zoom_plugin = WaveSurfer.Zoom.create({
          // the amount of zoom per wheel step, e.g. 0.5 means a 50% magnification per scroll
          scale: 0.5,
          // Optionally, specify the maximum pixels-per-second factor while zooming
          maxZoom: 500,
        });
        const hover_plugin = WaveSurfer.Hover.create({
          lineColor: '#ff0000',
          lineWidth: 2,
          labelBackground: '#555',
          labelColor: '#fff',
          labelSize: '11px',
        });
        // Add the plugin to the WaveSurfer instance
        config.plugins = [
          regions_plugin,
          timeline,
          zoom_plugin,
          hover_plugin,
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
            region.fixed_size = region_config.fixed_size;
            regions.push(region);
          });
          let previous_region = null;
          regions.forEach((region, index) => {
            if (previous_region) {
              previous_region.next_region = region;
              region.previous_region = previous_region;
            }
            previous_region = region;
          });
        });

        ws.on('error', e => alert(e))

        regions_plugin.on('region-clicked', (region, e) => {
          e.stopPropagation() // prevent triggering a click on the waveform
          region.play(true)
        })

        function updatePreviousRegion(region) {
          if (region.previous_region) {
            region.previous_region.setOptions({end: region.start});
            if (region.previous_region.fixed_size) {
              region.previous_region.setOptions({start: region.start - region.previous_region.fixed_size});
            }
            updatePreviousRegion(region.previous_region);
          }
        }

        function updateNextRegion(region) {
          if (region.next_region) {
            region.next_region.setOptions({start: region.end});
            if (region.next_region.fixed_size) {
              region.next_region.setOptions({end: region.end + region.next_region.fixed_size});
            }
            updateNextRegion(region.next_region);
          }
        }
        regions_plugin.on('region-update', (region, side) => {
          if (region.previous_region) {
            updatePreviousRegion(region);
          }
          if (region.next_region) {
            region.next_region.setOptions({start: region.end});
            updateNextRegion(region);
          }
        });
      });
    }
  }
})(Drupal, WaveSurfer, once);