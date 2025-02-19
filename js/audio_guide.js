// import RegionsPlugin from 'https://unpkg.com/wavesurfer.js@7.9.1/dist/plugins/regions.min.js';

(function(Drupal){
  Drupal.behaviors.audio_guide = {
    attach: function(context, settings) {
      once('wavesurfer', '.audio-guide-track').forEach(function (element) {
        const hidden_input = element.nextElementSibling
        const common_region_min_width = 0.1;
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


        let region_configs = JSON.parse(hidden_input.value);
        // Create a region
        let regions = [];
        ws.on('decode', function (duration) {
          region_configs.forEach((region_config) => {
            if (!('color' in region_config)) {
              region_config.color = randomColor();
            }
            let region = regions_plugin.addRegion(region_config);
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
            preventRegionCollapsing(region.previous_region, 'end');
            preventOverlapping(region.previous_region, 'end');
            updatePreviousRegion(region.previous_region);
          }
        }

        function updateNextRegion(region) {
          if (region.next_region) {
            region.next_region.setOptions({start: region.end});
            if (region.next_region.fixed_size) {
              region.next_region.setOptions({end: region.end + region.next_region.fixed_size});
            }
            preventRegionCollapsing(region.next_region, 'start');
            preventOverlapping(region.next_region, 'start');
            updateNextRegion(region.next_region);
          }
        }

        /**
         * Ensure the region not becomming less than minimum width.
         *
         * @param {*} region 
         * @param {*} side 
         */
        function preventRegionCollapsing(region, side) {
          let limit;
          let current_region_min_width = region.fixed_size ? region.fixed_size : common_region_min_width;
          if (side === 'start') {
            limit = region.end - current_region_min_width;
            if (region.start > limit) {
              region.setOptions({start: limit});
            }
          }
          else if (side === 'end') {
            limit = region.start + current_region_min_width;
            if (region.end < limit) {
              region.setOptions({end: limit});
            }
          }
        }

        /**
         * Ensure the region go beyon bounderies of previous and next regions.
         *
         * @param {*} region 
         * @param {*} side
         */
        function preventOverlapping(region, side) {
          let limit;
          if (side === 'start' && region.previous_region) {
            limit = region.previous_region.start + common_region_min_width;
            if (region.start < limit) {
              region.setOptions({start: limit});
            }
          }
          else if (side === 'end' && region.next_region) {
            limit = region.next_region.end - common_region_min_width;
            if (region.end > limit) {
              region.setOptions({end: limit});
            }
          }
          else {
            // Dragging
            if (region.previous_region) {
              // Don't allow this go beyond making the previous region less than minim width.
              let limit = region.previous_region.start + common_region_min_width;
              if (region.start < limit) {
                region.setOptions({start: limit});
              }
            }
            if (region.next_region) {
              // Don't allow this go beyond making the next region less than minim width.
              limit = region.next_region.end - common_region_min_width;
              if (region.end > limit) {
                region.setOptions({end: limit});
              }
            }
          }
        }

        /**
         * Keep watching while regions are resized and dragged.
         */
        regions_plugin.on('region-update', (region, side) => {
          // First ensure that this region not collapsing.
          preventRegionCollapsing(region, side);
          // Then ensure it does not go beyond previous and next regions.
          preventOverlapping(region, side);
          let limit;
          if (region.fixed_size) {
            // This is fixed sized region like transitions.
            if (side === 'start') {
              if (region.next_region) {
                // Don't allow to crush next region.
                limit = region.next_region.end - common_region_min_width - region.fixed_size;
                if (region.start > limit) {
                  region.setOptions({start: limit});
                }
              }
              region.setOptions({end: region.start + region.fixed_size});
            }
            if (side === 'end') {
              if (region.previous_region) {
                // Don't allow to crush previous region.
                limit = region.previous_region.start + common_region_min_width + region.fixed_size;
                if (region.end < limit) {
                  region.setOptions({end: limit});
                }
              }
              region.setOptions({start: region.end - region.fixed_size});
            }
          }
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