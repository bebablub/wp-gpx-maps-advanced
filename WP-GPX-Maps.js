/*
 * WP-GPX-Maps frontend: Google Maps track rendering plus Highcharts charts.
 * Vanilla ES2017, no dependencies besides Highcharts and the Google Maps JS API.
 */
(function () {
  "use strict";

  const SETTINGS = window.WPGPXMAPS_SETTINGS || {};
  const I18N = SETTINGS.i18n || {};
  const IMAGE_SIZE = 38;
  const IMAGE_SIZE_HOVER = 94;
  const IMAGE_CONCURRENCY = 3;

  let googlePromise = null;
  let infoWindow = null;
  let ImageOverlay = null;

  // ------------------------------------------------------------------ helpers

  const byId = (id) => document.getElementById(id);

  const fmt = (value, dec) =>
    Number(value).toLocaleString(undefined, {
      minimumFractionDigits: dec,
      maximumFractionDigits: dec,
    });

  const esc = (text) =>
    String(text).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );

  const isNumber = (value) =>
    typeof value === "number" && Number.isFinite(value);

  const clear = (el) => {
    while (el.firstChild) {
      el.removeChild(el.firstChild);
    }
  };

  /** Approximate (equirectangular) squared distance, good enough to find the nearest track point. */
  function distance2(a, lat, lng) {
    const dLat = a.lat - lat;
    const dLng = (a.lng - lng) * Math.cos((lat * Math.PI) / 180);
    return dLat * dLat + dLng * dLng;
  }

  function throttle(fn, wait) {
    let last = 0;
    return (...args) => {
      const now = Date.now();
      if (now - last >= wait) {
        last = now;
        fn(...args);
      }
    };
  }

  let colorContext = null;

  /** Resolves any CSS color to [r, g, b]. */
  function toRgb(color) {
    if (!colorContext) {
      colorContext = document.createElement("canvas").getContext("2d");
    }
    colorContext.fillStyle = "#3366cc";
    colorContext.fillStyle = String(color);
    const value = colorContext.fillStyle;
    if (value.startsWith("#")) {
      const n = parseInt(value.slice(1), 16);
      return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }
    const match = value.match(/\d+(\.\d+)?/g);
    return match ? match.slice(0, 3).map(Number) : [51, 102, 204];
  }

  /** Blends `from` towards red; alpha 0 keeps the base color. */
  function blendToRed(from, alpha) {
    const [r, g, b] = toRgb(from);
    const mix = (c, target) => Math.round(c + (target - c) * alpha);
    return `rgb(${mix(r, 255)},${mix(g, 0)},${mix(b, 0)})`;
  }

  // ------------------------------------------------------------- Google Maps

  function loadGoogle() {
    if (googlePromise) {
      return googlePromise;
    }

    const apiReady = () =>
      window.google &&
      google.maps &&
      typeof google.maps.importLibrary === "function";

    googlePromise = new Promise((resolve, reject) => {
      if (apiReady()) {
        resolve();
        return;
      }

      if (SETTINGS.loadMaps === false) {
        // Another script is expected to load the API; wait for it.
        let tries = 0;
        const timer = setInterval(() => {
          if (apiReady()) {
            clearInterval(timer);
            resolve();
          } else if (++tries > 100) {
            clearInterval(timer);
            reject(new Error("Google Maps API not available"));
          }
        }, 100);
        return;
      }

      const params = new URLSearchParams({
        loading: "async",
        libraries: "marker",
        v: SETTINGS.channel || "quarterly",
        callback: "__wpgpxmapsGoogleReady",
      });
      if (SETTINGS.apiKey) {
        params.set("key", SETTINGS.apiKey);
      }
      if (SETTINGS.language) {
        params.set("language", SETTINGS.language);
      }

      window.__wpgpxmapsGoogleReady = () => {
        delete window.__wpgpxmapsGoogleReady;
        resolve();
      };

      const script = document.createElement("script");
      script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
      script.async = true;
      script.onerror = () =>
        reject(new Error("Google Maps API failed to load"));
      document.head.appendChild(script);
    }).then(() =>
      Promise.all([
        google.maps.importLibrary("core"),
        google.maps.importLibrary("maps"),
        google.maps.importLibrary("marker"),
      ]),
    );

    googlePromise.catch(() => {
      googlePromise = null;
    });
    return googlePromise;
  }

  const useAdvancedMarkers = () =>
    Boolean(
      SETTINGS.mapId &&
      google.maps.marker &&
      google.maps.marker.AdvancedMarkerElement,
    );

  // Same look as before: yellow mountain pin, red rocket pin; start/end/waypoint/GPS get simple pins
  // unless the shortcode or the settings provide an image.
  const PINS = {
    altitude: {
      glyph: "\u26F0",
      background: "#f9ab00",
      borderColor: "#c97d00",
      glyphColor: "#ffffff",
    },
    speed: {
      glyph: "\uD83D\uDE80",
      background: "#d93025",
      borderColor: "#9b1c17",
      glyphColor: "#ffffff",
    },
    start: {
      glyph: "\u25B6",
      background: "#188038",
      borderColor: "#0b5d27",
      glyphColor: "#ffffff",
    },
    end: {
      glyph: "\uD83C\uDFC1",
      background: "#3c4043",
      borderColor: "#202124",
      glyphColor: "#ffffff",
    },
    waypoint: {
      glyph: "",
      background: "#188038",
      borderColor: "#0b5d27",
      glyphColor: "#ffffff",
    },
    gps: {
      glyph: "",
      background: "#4285f4",
      borderColor: "#1a56c4",
      glyphColor: "#ffffff",
    },
  };
  const DEFAULT_CURRENT_ICON =
    "https://maps.google.com/mapfiles/kml/pal4/icon25.png";

  /** Builds a PinElement without using the deprecated `glyph` / `element` members. */
  function createPin(pin) {
    const PinElement = google.maps.marker.PinElement;
    const options = {
      background: pin.background,
      borderColor: pin.borderColor,
      glyphColor: pin.glyphColor,
    };
    if (pin.glyph) {
      options["glyphText" in PinElement.prototype ? "glyphText" : "glyph"] =
        pin.glyph;
    }
    const element = new PinElement(options);
    return element instanceof HTMLElement ? element : element.element;
  }

  /**
   * Creates a marker (AdvancedMarkerElement when a Map ID is set, classic Marker otherwise).
   * Options: map, position, title, zIndex, iconUrl (image) or pin {glyph, background, borderColor,
   * glyphColor}; the classic Marker draws a coloured circle with the glyph instead of a pin.
   */
  function createMarker(map, o) {
    if (useAdvancedMarkers()) {
      let content;
      if (o.pin) {
        content = createPin(o.pin);
      } else if (o.iconUrl) {
        content = document.createElement("img");
        content.src = o.iconUrl;
        content.alt = "";
        content.style.width = "32px";
        content.style.height = "32px";
      }
      const m = new google.maps.marker.AdvancedMarkerElement({
        map,
        position: o.position,
        title: o.title || "",
        zIndex: o.zIndex,
        content,
      });
      return {
        raw: m,
        setPosition: (p) => {
          m.position = p;
        },
        setTitle: (t) => {
          m.title = t;
        },
        onClick: (fn) => m.addEventListener("gmp-click", fn),
        onHover: (enter, leave) => {
          const el = m.content || m;
          el.addEventListener("mouseenter", enter);
          el.addEventListener("mouseleave", leave);
        },
      };
    }

    const options = {
      map,
      position: o.position,
      title: o.title || "",
      zIndex: o.zIndex,
    };
    if (o.pin) {
      options.icon = {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 9,
        fillColor: o.pin.background,
        fillOpacity: 1,
        strokeColor: o.pin.borderColor,
        strokeWeight: 2,
      };
      if (o.pin.glyph) {
        options.label = {
          text: o.pin.glyph,
          color: o.pin.glyphColor,
          fontWeight: "bold",
          fontSize: "12px",
        };
      }
    } else if (o.iconUrl) {
      options.icon = o.iconUrl;
    }
    const m = new google.maps.Marker(options);
    return {
      raw: m,
      setPosition: (p) => m.setPosition(p),
      setTitle: (t) => m.setTitle(t),
      onClick: (fn) => m.addListener("click", fn),
      onHover: (enter, leave) => {
        m.addListener("mouseover", enter);
        m.addListener("mouseout", leave);
      },
    };
  }

  function openInfo(map, marker, content) {
    if (infoWindow) {
      infoWindow.close();
    }
    infoWindow = new google.maps.InfoWindow({ content });
    infoWindow.open({ map, anchor: marker.raw });
  }

  function closeInfo() {
    if (infoWindow) {
      infoWindow.close();
      infoWindow = null;
    }
  }

  /** Builds <div>lines…</div> from plain strings (bold first line optional). */
  function infoContent(title, lines) {
    const box = document.createElement("div");
    if (title) {
      const b = document.createElement("b");
      b.textContent = title;
      box.appendChild(b);
    }
    lines.forEach((line) => {
      if (box.firstChild) {
        box.appendChild(document.createElement("br"));
      }
      box.appendChild(document.createTextNode(line));
    });
    return box;
  }

  function waypointContent(w) {
    const box = document.createElement("div");
    if (w.name) {
      const b = document.createElement("b");
      b.textContent = w.name;
      box.appendChild(b);
    }
    if (w.desc) {
      const d = document.createElement("div");
      d.style.whiteSpace = "pre-line";
      d.textContent = w.desc;
      box.appendChild(d);
    }
    const p = document.createElement("p");
    const a = document.createElement("a");
    a.href = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${w.lat},${w.lon}`)}`;
    a.target = "_blank";
    a.rel = "noopener noreferrer";
    a.textContent = I18N.directions || "Directions";
    p.appendChild(a);
    box.appendChild(p);
    return box;
  }

  // ------------------------------------------------------------- tile layers

  const TILE_SOURCES = {
    OSM1: {
      name: "OSM",
      url: (z, x, y) => `https://tile.openstreetmap.org/${z}/${x}/${y}.png`,
      maxZoom: 19,
    },
    OSM2: {
      name: "OSM Cycle",
      key: "tfKey",
      url: (z, x, y, k) =>
        `https://tile.thunderforest.com/cycle/${z}/${x}/${y}.png?apikey=${encodeURIComponent(k)}`,
      credit: ["Thunderforest", "https://www.thunderforest.com/"],
    },
    OSM4: {
      name: "OSM Transport",
      key: "tfKey",
      url: (z, x, y, k) =>
        `https://tile.thunderforest.com/transport/${z}/${x}/${y}.png?apikey=${encodeURIComponent(k)}`,
      credit: ["Thunderforest", "https://www.thunderforest.com/"],
    },
    OSM5: {
      name: "OSM Landscape",
      key: "tfKey",
      url: (z, x, y, k) =>
        `https://tile.thunderforest.com/landscape/${z}/${x}/${y}.png?apikey=${encodeURIComponent(k)}`,
      credit: ["Thunderforest", "https://www.thunderforest.com/"],
    },
    OSM6: {
      name: "MapTiler Outdoor",
      key: "mtKey",
      url: (z, x, y, k) =>
        `https://api.maptiler.com/maps/outdoor-v2/256/${z}/${x}/${y}.png?key=${encodeURIComponent(k)}`,
      credit: ["MapTiler", "https://www.maptiler.com/copyright/"],
    },
    OSM7: {
      name: "MapTiler Topo",
      key: "mtKey",
      url: (z, x, y, k) =>
        `https://api.maptiler.com/maps/topo-v2/256/${z}/${x}/${y}.png?key=${encodeURIComponent(k)}`,
      credit: ["MapTiler", "https://www.maptiler.com/copyright/"],
    },
    OSM8: {
      name: "MapTiler Landscape",
      key: "mtKey",
      url: (z, x, y, k) =>
        `https://api.maptiler.com/maps/landscape/256/${z}/${x}/${y}.png?key=${encodeURIComponent(k)}`,
      credit: ["MapTiler", "https://www.maptiler.com/copyright/"],
    },
  };

  /** Registers the tile layers that are usable with the configured keys; returns their ids. */
  function registerTileLayers(map) {
    const available = [];
    Object.keys(TILE_SOURCES).forEach((id) => {
      const source = TILE_SOURCES[id];
      const key = source.key ? SETTINGS[source.key] : "";
      if (source.key && !key) {
        return;
      }
      map.mapTypes.set(
        id,
        new google.maps.ImageMapType({
          name: source.name,
          alt: source.name,
          tileSize: new google.maps.Size(256, 256),
          maxZoom: source.maxZoom || 18,
          getTileUrl: (coord, zoom) => {
            const tiles = 2 ** zoom;
            if (coord.y < 0 || coord.y >= tiles) {
              return "";
            }
            const x = ((coord.x % tiles) + tiles) % tiles;
            return source.url(zoom, x, coord.y, key);
          },
        }),
      );
      available.push(id);
    });
    return available;
  }

  function setupOsmFooter(map, id, tileIds) {
    const footer = byId(`wpgpxmaps_${id}_osm_footer`);
    if (!footer) {
      return;
    }
    const span = footer.querySelector("span");
    let creditNode = null;
    const update = () => {
      const current = map.getMapTypeId();
      const isTile = tileIds.includes(current);
      footer.style.display = isTile ? "" : "none";
      if (creditNode && creditNode.parentNode) {
        creditNode.parentNode.removeChild(creditNode);
        creditNode = null;
      }
      const credit =
        isTile && TILE_SOURCES[current] && TILE_SOURCES[current].credit;
      if (credit && span) {
        creditNode = document.createElement("span");
        creditNode.style.cssText = "position:static;padding:0";
        const link = document.createElement("a");
        link.href = credit[1];
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = credit[0];
        creditNode.append(" | \u00A9 ", link);
        span.appendChild(creditNode);
      }
    };
    map.addListener("maptypeid_changed", update);
    update();
  }

  // ---------------------------------------------------------- image overlays

  function createImageOverlayClass() {
    return class WpgpxImageOverlay extends google.maps.OverlayView {
      constructor(map, position, src, href) {
        super();
        this._latlng = new google.maps.LatLng(position.lat, position.lng);
        this._src = src;
        this._href = href;
        this._div = null;
        this.setMap(map);
      }

      onAdd() {
        const img = document.createElement("img");
        img.className = "myngimages";
        img.src = this._src;
        img.alt = "";
        img.style.width = `${IMAGE_SIZE}px`;
        img.style.height = `${IMAGE_SIZE}px`;
        img.style.objectFit = "cover";
        img.addEventListener("mouseenter", () => {
          img.style.width = `${IMAGE_SIZE_HOVER}px`;
          img.style.height = `${IMAGE_SIZE_HOVER}px`;
          img.style.zIndex = "10";
        });
        img.addEventListener("mouseleave", () => {
          img.style.width = `${IMAGE_SIZE}px`;
          img.style.height = `${IMAGE_SIZE}px`;
          img.style.zIndex = "";
        });
        if (this._href) {
          img.addEventListener("click", () => {
            window.open(this._href, "_blank", "noopener");
          });
        }
        this._div = img;
        this.getPanes().overlayMouseTarget.appendChild(img);
      }

      draw() {
        const point = this.getProjection().fromLatLngToDivPixel(this._latlng);
        if (point && this._div) {
          this._div.style.left = `${point.x}px`;
          this._div.style.top = `${point.y}px`;
        }
      }

      onRemove() {
        if (this._div && this._div.parentNode) {
          this._div.parentNode.removeChild(this._div);
        }
        this._div = null;
      }

      setVisible(visible) {
        if (this._div) {
          this._div.style.display = visible ? "" : "none";
        }
      }
    };
  }

  /** Picks the smallest usable source of a (possibly lazy-loaded) thumbnail. */
  function thumbnailUrl(img) {
    const attrs = ["src", "data-src", "data-lazy-src", "data-original"];
    for (const attr of attrs) {
      const value = img.getAttribute(attr);
      if (value && !value.startsWith("data:")) {
        return value;
      }
    }
    const srcset =
      img.getAttribute("srcset") ||
      img.getAttribute("data-srcset") ||
      img.getAttribute("data-lazy-srcset");
    if (srcset) {
      const first = srcset.split(",")[0].trim().split(/\s+/)[0];
      if (first) {
        return first;
      }
    }
    return "";
  }

  function setupImages(ctx) {
    const container = byId(`ngimages_${ctx.cfg.id}`);
    if (!container) {
      return;
    }
    const items = [];
    container.querySelectorAll("span[data-lat]").forEach((span) => {
      const lat = parseFloat(span.getAttribute("data-lat"));
      const lng = parseFloat(span.getAttribute("data-lon"));
      const img = span.querySelector("img");
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || !img) {
        return;
      }
      const src = thumbnailUrl(img);
      if (!src) {
        return;
      }
      const anchor = span.querySelector("a");
      items.push({
        position: { lat, lng },
        src,
        href: anchor && /^https?:/i.test(anchor.href) ? anchor.href : "",
      });
    });
    if (!items.length) {
      return;
    }

    ImageOverlay = ImageOverlay || createImageOverlayClass();
    const overlays = [];
    let visible = true;
    let next = 0;

    const loadNext = () => {
      if (next >= items.length) {
        return;
      }
      const item = items[next++];
      const probe = new Image();
      const done = () => loadNext();
      probe.onload = () => {
        const overlay = new ImageOverlay(
          ctx.map,
          item.position,
          item.src,
          item.href,
        );
        overlays.push(overlay);
        if (!visible) {
          overlay.setVisible(false);
        }
        done();
      };
      probe.onerror = done;
      probe.src = item.src;
    };
    for (let i = 0; i < IMAGE_CONCURRENCY; i++) {
      loadNext();
    }

    const button = makeControlButton(
      "imagesOff",
      I18N.hideImages || "Hide Images",
    );
    button.addEventListener("click", () => {
      visible = !visible;
      overlays.forEach((o) => o.setVisible(visible));
      const label = visible
        ? I18N.hideImages || "Hide Images"
        : I18N.showImages || "Show Images";
      setControlIcon(button, visible ? "imagesOff" : "images");
      button.title = label;
      button.setAttribute("aria-label", label);
    });
    ctx.map.controls[google.maps.ControlPosition.RIGHT_CENTER].push(button);
  }

  // Line icons (24x24); drawn with the button's text colour so they are always visible.
  const CONTROL_ICONS = {
    images: [
      ["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }],
      ["circle", { cx: 9, cy: 10, r: 1.5 }],
      ["path", { d: "M21 16l-5-5-8 8" }],
    ],
    imagesOff: [
      ["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }],
      ["circle", { cx: 9, cy: 10, r: 1.5 }],
      ["path", { d: "M21 16l-5-5-8 8" }],
      ["path", { d: "M3 3l18 18" }],
    ],
    center: [
      ["circle", { cx: 12, cy: 12, r: 4 }],
      ["path", { d: "M12 2v4M12 18v4M2 12h4M18 12h4" }],
    ],
  };

  function setControlIcon(button, name) {
    const NS = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(NS, "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("width", "22");
    svg.setAttribute("height", "22");
    svg.setAttribute("fill", "none");
    svg.setAttribute("stroke", "currentColor");
    svg.setAttribute("stroke-width", "2");
    svg.setAttribute("stroke-linecap", "round");
    svg.setAttribute("stroke-linejoin", "round");
    svg.setAttribute("aria-hidden", "true");
    svg.style.display = "block";
    (CONTROL_ICONS[name] || []).forEach(([tag, attrs]) => {
      const node = document.createElementNS(NS, tag);
      Object.keys(attrs).forEach((key) => node.setAttribute(key, attrs[key]));
      svg.appendChild(node);
    });
    clear(button);
    button.appendChild(svg);
  }

  /** Square white map control in the style of the Google Maps buttons. */
  function makeControlButton(icon, label) {
    const button = document.createElement("button");
    button.type = "button";
    button.title = label;
    button.setAttribute("aria-label", label);
    button.style.cssText =
      "display:flex;align-items:center;justify-content:center;width:40px;height:40px;margin:10px;padding:0;border:0;border-radius:2px;background:#fff;color:#666;box-shadow:0 1px 4px -1px rgba(0,0,0,.3);cursor:pointer";
    button.addEventListener("mouseenter", () => {
      button.style.color = "#000";
    });
    button.addEventListener("mouseleave", () => {
      button.style.color = "#666";
    });
    setControlIcon(button, icon);
    return button;
  }

  // ------------------------------------------------------------------- track

  /** Moving average of the grade values used to colour the track. */
  function smoothGrades(values, window3) {
    return values.map((value, i) => {
      if (!isNumber(value)) {
        return null;
      }
      let sum = 0;
      let count = 0;
      for (
        let j = Math.max(0, i - window3);
        j <= Math.min(values.length - 1, i + window3);
        j++
      ) {
        if (isNumber(values[j])) {
          sum += values[j];
          count++;
        }
      }
      return count ? sum / count : null;
    });
  }

  /** Great-circle distance in meters. */
  function haversine(a, b) {
    const rad = Math.PI / 180;
    const dLat = (b.lat - a.lat) * rad;
    const dLng = (b.lng - a.lng) * rad;
    const h =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) ** 2;
    return 12742000 * Math.asin(Math.min(1, Math.sqrt(h)));
  }

  const MAX_ARROWS_PER_BLOCK = 150;
  const ARROW_DIRECTION_SPAN = 25; // meters of track used to get a stable heading

  /**
   * Draws a direction arrow every `spacing` meters along `path`. Each arrow is a single symbol on a
   * short invisible line, so the spacing does not depend on how the map renders repeated symbols.
   */
  function drawArrows(map, path, color, spacing) {
    if (!(spacing > 0) || path.length < 2) {
      return;
    }
    const cumulative = [0];
    for (let i = 1; i < path.length; i++) {
      cumulative.push(cumulative[i - 1] + haversine(path[i - 1], path[i]));
    }
    const total = cumulative[cumulative.length - 1];
    if (total <= 0) {
      return;
    }
    const step = Math.max(spacing, total / MAX_ARROWS_PER_BLOCK);
    const icon = {
      path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
      scale: 2,
      strokeColor: color,
      strokeOpacity: 1,
      strokeWeight: 2,
    };

    let segment = 1;
    for (let at = step / 2; at < total; at += step) {
      while (segment < path.length - 1 && cumulative[segment] < at) {
        segment++;
      }
      let from = segment - 1;
      let to = segment;
      while (
        from > 0 &&
        cumulative[to] - cumulative[from] < ARROW_DIRECTION_SPAN
      ) {
        from--;
      }
      while (
        to < path.length - 1 &&
        cumulative[to] - cumulative[from] < ARROW_DIRECTION_SPAN
      ) {
        to++;
      }
      const span = cumulative[to] - cumulative[from];
      if (span <= 0) {
        continue;
      }
      const offset = ((at - cumulative[from]) / span) * 100;
      new google.maps.Polyline({
        map,
        path: [path[from], path[to]],
        strokeOpacity: 0,
        clickable: false,
        icons: [{ icon, offset: `${Math.max(0, Math.min(100, offset))}%` }],
      });
    }
  }

  function drawTrack(ctx) {
    const { cfg, map, pts } = ctx;
    const colors =
      Array.isArray(cfg.colors) && cfg.colors.length ? cfg.colors : ["#3366cc"];
    const grades =
      cfg.elevColor &&
      cfg.elevColor.enabled &&
      cfg.mapGrade &&
      cfg.mapGrade.length === pts.length
        ? smoothGrades(cfg.mapGrade, 2)
        : null;
    const threshold = cfg.elevColor ? Number(cfg.elevColor.threshold) || 0 : 0;
    const maxGrade = cfg.elevColor ? Number(cfg.elevColor.max) || 12 : 12;
    const onHover = throttle((event) => {
      if (event && event.latLng) {
        ctx.moveToLatLng(event.latLng.lat(), event.latLng.lng());
      }
    }, 60);

    const addPolyline = (path, color, icons) => {
      const poly = new google.maps.Polyline({
        map,
        path,
        strokeColor: color,
        strokeOpacity: 0.7,
        strokeWeight: 4,
        icons: icons || undefined,
      });
      poly.addListener("mouseover", onHover);
      poly.addListener("mousemove", onHover);
      return poly;
    };

    const arrowSpacing =
      Number(cfg.arrowKm) > 0 ? Number(cfg.arrowKm) * 1000 : 0;

    const drawBlock = (indices, blockNumber) => {
      if (indices.length < 2) {
        return;
      }
      const base = colors[blockNumber % colors.length];
      const blockPath = indices.map((i) => pts[i]);
      if (!grades) {
        addPolyline(blockPath, base, null);
        drawArrows(map, blockPath, base, arrowSpacing);
        return;
      }

      let path = [];
      let bucket = null;
      const flush = () => {
        if (path.length > 1) {
          addPolyline(path, bucket > 0 ? blendToRed(base, bucket) : base, null);
        }
      };
      indices.forEach((i) => {
        const grade = grades[i];
        let alpha = 0;
        if (isNumber(grade) && grade > threshold) {
          alpha = Math.min(
            1,
            (grade - threshold) / Math.max(0.1, maxGrade - threshold),
          );
        }
        const quantised = Math.round(alpha * 5) / 5;
        if (bucket === null) {
          bucket = quantised;
        } else if (quantised !== bucket) {
          path.push(pts[i]);
          flush();
          path = [pts[i]];
          bucket = quantised;
          return;
        }
        path.push(pts[i]);
      });
      flush();
      drawArrows(map, blockPath, base, arrowSpacing);
    };

    let block = [];
    let blockNumber = 0;
    pts.forEach((p, i) => {
      if (p) {
        block.push(i);
      } else if (block.length) {
        drawBlock(block, blockNumber++);
        block = [];
      }
    });
    if (block.length) {
      drawBlock(block, blockNumber);
    }
  }

  function drawMarkers(ctx) {
    const { cfg, map, pts } = ctx;
    const icons = cfg.icons || {};
    const first = pts.find(Boolean);
    const last = [...pts].reverse().find(Boolean);

    if (first) {
      createMarker(map, {
        position: first,
        title: I18N.start || "Start",
        iconUrl: icons.start || "",
        pin: icons.start ? null : PINS.start,
        zIndex: 2,
      });
    }
    if (last && last !== first) {
      createMarker(map, {
        position: last,
        title: I18N.end || "End",
        iconUrl: icons.end || "",
        pin: icons.end ? null : PINS.end,
        zIndex: 3,
      });
    }
    if (first) {
      ctx.current = createMarker(map, {
        position: first,
        title: I18N.currentPosition || "",
        iconUrl: icons.current || DEFAULT_CURRENT_ICON,
        zIndex: 4,
      });
    }

    const extremes = cfg.extremes || {};
    const series = cfg.chart || {};
    const eleText = (index, own) => {
      const v = isNumber(own) ? own : (series.ele || [])[index];
      return isNumber(v) ? `${fmt(v, 0)} ${cfg.units.elevation}` : "-";
    };
    const speedText = (index, own) => {
      const v = isNumber(own) ? own : (series.speed || [])[index];
      return isNumber(v)
        ? `${fmt(v, speedDecimals(cfg))} ${cfg.units.speed}`
        : "-";
    };
    const addExtreme = (extreme, title, pin, isElevation) => {
      if (!extreme || !pts[extreme.index]) {
        return;
      }
      const marker = createMarker(map, {
        position: pts[extreme.index],
        title,
        pin,
        zIndex: 3,
      });
      const content = () =>
        infoContent(title, [
          `${I18N.altitude || "Altitude"}: ${eleText(extreme.index, isElevation ? extreme.value : null)}`,
          `${I18N.speed || "Speed"}: ${speedText(extreme.index, isElevation ? null : extreme.value)}`,
        ]);
      marker.onHover(() => openInfo(map, marker, content()), closeInfo);
      marker.onClick(() => openInfo(map, marker, content()));
    };
    addExtreme(
      extremes.maxEle,
      I18N.maxAltitude || "Max altitude",
      PINS.altitude,
      true,
    );
    addExtreme(
      extremes.maxSpeed,
      I18N.maxSpeed || "Max speed",
      PINS.speed,
      false,
    );

    (cfg.waypoints || []).forEach((w) => {
      if (!isNumber(w.lat) || !isNumber(w.lon)) {
        return;
      }
      const position = { lat: w.lat, lng: w.lon };
      ctx.bounds.extend(position);
      const marker = createMarker(map, {
        position,
        title: w.name || "",
        iconUrl: w.img || "",
        pin: w.img ? null : PINS.waypoint,
        zIndex: 5,
      });
      marker.onClick(() => openInfo(map, marker, waypointContent(w)));
    });
  }

  function speedDecimals(cfg) {
    return cfg.units.paceSpeed || cfg.units.speed === "knots" ? 2 : 0;
  }

  function setupControls(ctx) {
    const { map, bounds } = ctx;
    const button = makeControlButton(
      "center",
      I18N.backToCenter || "Back to center",
    );
    button.style.display = "none";
    let fitting = true; // the initial fit also moves the center

    button.addEventListener("click", () => {
      fitting = true;
      button.style.display = "none";
      fitToBounds(map, bounds);
    });
    map.controls[google.maps.ControlPosition.RIGHT_CENTER].push(button);

    map.addListener("idle", () => {
      fitting = false;
    });
    map.addListener("center_changed", () => {
      if (!fitting) {
        button.style.display = "flex";
      }
    });
  }

  function fitToBounds(map, bounds) {
    if (bounds.isEmpty()) {
      map.setCenter({ lat: 0, lng: 0 });
      map.setZoom(2);
      return;
    }
    map.fitBounds(bounds);
    google.maps.event.addListenerOnce(map, "idle", () => {
      if (map.getZoom() > 17) {
        map.setZoom(17);
      }
    });
  }

  function setupGps(ctx) {
    if (!ctx.cfg.usePosition || !navigator.geolocation) {
      return;
    }
    let marker = null;
    navigator.geolocation.watchPosition(
      (position) => {
        const point = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };
        if (marker) {
          marker.setPosition(point);
          return;
        }
        marker = createMarker(ctx.map, {
          position: point,
          title: I18N.currentPosition || "",
          iconUrl: (ctx.cfg.icons && ctx.cfg.icons.gps) || "",
          pin: ctx.cfg.icons && ctx.cfg.icons.gps ? null : PINS.gps,
          zIndex: 6,
        });
        ctx.bounds.extend(point);
        fitToBounds(ctx.map, ctx.bounds);
      },
      () => {},
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 },
    );
  }

  // ------------------------------------------------------------------- chart

  const SERIES = [
    { key: "ele", label: "altitude", color: "ele", opposite: false },
    { key: "speed", label: "speed", color: "speed", opposite: true },
    {
      key: "hr",
      label: "heartRate",
      color: "hr",
      opposite: true,
      suffix: "",
      dec: 0,
    },
    {
      key: "atemp",
      label: "atemp",
      color: "atemp",
      opposite: true,
      suffix: "\u00B0C",
      dec: 1,
    },
    {
      key: "cad",
      label: "cadence",
      color: "cad",
      opposite: true,
      suffix: "",
      dec: 0,
    },
    {
      key: "grade",
      label: "grade",
      color: "grade",
      opposite: true,
      suffix: "%",
      dec: 1,
    },
  ];

  function setupChart(ctx) {
    const { cfg } = ctx;
    const container = byId(`hchart_${cfg.id}`);
    const data = cfg.chart;
    if (
      !container ||
      !data ||
      !Array.isArray(data.dist) ||
      !data.dist.length ||
      typeof window.Highcharts === "undefined"
    ) {
      if (container) {
        container.style.display = "none";
      }
      return;
    }

    const units = cfg.units;
    const distFmt = {
      suffix: units.distance,
      dec: units.distance === "m" ? 0 : 1,
    };
    const range = cfg.range || {};

    // Chart rows are the points with a known distance; rowToIndex maps them back to the map index.
    const rowToIndex = [];
    const indexToRow = new Map();
    data.dist.forEach((d, i) => {
      if (isNumber(d)) {
        indexToRow.set(i, rowToIndex.length);
        rowToIndex.push(i);
      }
    });

    const yAxis = [];
    const series = [];
    SERIES.forEach((def) => {
      const values = data[def.key];
      if (!Array.isArray(values) || !values.length) {
        return;
      }
      let fmtDef;
      if (def.key === "ele") {
        fmtDef = { suffix: ` ${units.elevation}`, dec: 0 };
      } else if (def.key === "speed") {
        fmtDef = { suffix: ` ${units.speed}`, dec: speedDecimals(cfg) };
      } else {
        fmtDef = { suffix: def.suffix, dec: def.dec };
      }

      const rows = rowToIndex.map((i) => [
        data.dist[i],
        isNumber(values[i]) ? values[i] : null,
      ]);
      const axis = {
        title: { text: null },
        labels: {
          formatter() {
            return fmt(this.value, fmtDef.dec) + fmtDef.suffix;
          },
        },
        opposite: def.opposite,
      };

      const from =
        def.key === "ele"
          ? range.ele && range.ele[0]
          : def.key === "speed"
            ? range.speed && range.speed[0]
            : null;
      const to =
        def.key === "ele"
          ? range.ele && range.ele[1]
          : def.key === "speed"
            ? range.speed && range.speed[1]
            : null;
      const numeric = rows.map((r) => r[1]).filter(isNumber);
      if (isNumber(from)) {
        axis.min = from;
        axis.startOnTick = false;
      } else if (def.key === "ele" && numeric.length) {
        axis.min = numeric.reduce((a, b) => (b < a ? b : a));
      }
      if (isNumber(to)) {
        axis.max = to;
        axis.endOnTick = false;
      } else if (def.key === "ele" && numeric.length) {
        axis.max = numeric.reduce((a, b) => (b > a ? b : a));
      }
      if (def.key === "ele") {
        axis.labels.align = "right";
        axis.labels.x = -16;
        axis.labels.reserveSpace = true;
      }

      yAxis.push(axis);
      series.push({
        name: I18N[def.label] || def.label,
        lineWidth: 1,
        marker: { radius: 0 },
        data: rows,
        color: cfg.colorsChart[def.color],
        yAxis: yAxis.length - 1,
        custom: { fmt: fmtDef, key: def.key },
      });
    });

    if (!series.length) {
      container.style.display = "none";
      return;
    }

    const report = byId(`report_${cfg.id}`);
    let selectionBox = null;
    if (report) {
      // Only the live zoom selection lives here; whole-track values belong to the summary.
      selectionBox = document.createElement("div");
      selectionBox.className = "wpgpxmaps_selection";
      report.appendChild(selectionBox);
    }

    const onSelection = (event) => {
      if (!selectionBox) {
        return undefined;
      }
      clear(selectionBox);
      const axis = event && event.xAxis && event.xAxis[0];
      if (!axis) {
        return undefined;
      }
      const addLine = (text) => {
        const line = document.createElement("div");
        line.textContent = text;
        selectionBox.appendChild(line);
      };
      addLine(
        `${I18N.zoom || "Zoom"}: ${fmt(axis.min, distFmt.dec)} ${distFmt.suffix} -> ${fmt(axis.max, distFmt.dec)} ${distFmt.suffix}`,
      );
      ctx.chart.series.forEach((s) => {
        let sum = 0;
        let count = 0;
        s.options.data.forEach((row) => {
          if (row[0] >= axis.min && row[0] <= axis.max && isNumber(row[1])) {
            sum += row[1];
            count++;
          }
        });
        if (count) {
          const f = s.options.custom.fmt;
          addLine(
            `${s.name} ${I18N.avgLabel || "avg"}: ${fmt(sum / count, f.dec)}${f.suffix}`,
          );
        }
      });
      return undefined;
    };

    // Follow the theme's text colour so labels stay readable on dark pages.
    const textColor = window.getComputedStyle(container).color || "#333333";
    const lineColor = "rgba(128, 128, 128, 0.35)";
    const labelStyle = { color: textColor };
    yAxis.forEach((axis) => {
      axis.labels.style = labelStyle;
      axis.gridLineColor = lineColor;
    });

    const options = {
      chart: {
        type: "area",
        backgroundColor: "transparent",
        zooming: { type: "x" },
        events: { selection: onSelection },
      },
      title: { text: null },
      lang: { resetZoom: I18N.resetZoom || "Reset zoom" },
      xAxis: {
        lineColor,
        tickColor: lineColor,
        labels: {
          style: labelStyle,
          formatter() {
            return fmt(this.value, distFmt.dec) + distFmt.suffix;
          },
        },
      },
      yAxis,
      legend: {
        align: "center",
        verticalAlign: "top",
        y: -5,
        floating: true,
        borderWidth: 0,
        itemStyle: { color: textColor },
        itemHoverStyle: { color: textColor },
      },
      tooltip: {
        shared: true,
        crosshairs: true,
        formatter() {
          let text = `<b>${esc(fmt(this.x, distFmt.dec) + distFmt.suffix)}</b><br />`;
          this.points.forEach((p) => {
            const f = p.series.userOptions.custom.fmt;
            text += `${esc(p.series.name)}: ${esc(fmt(p.y, f.dec) + f.suffix)}<br />`;
          });
          return text;
        },
      },
      plotOptions: {
        area: {
          fillOpacity: 0.1,
          connectNulls: true,
          marker: {
            enabled: false,
            symbol: "circle",
            radius: 2,
            states: { hover: { enabled: true } },
          },
        },
        series: {
          point: {
            events: {
              mouseOver() {
                const index = rowToIndex[this.index];
                if (index !== undefined && ctx.pts[index]) {
                  ctx.setMarker(ctx.pts[index]);
                }
              },
            },
          },
        },
      },
      credits: { enabled: false },
      series,
    };

    ctx.chart = window.Highcharts.chart(container, options);
    ctx.indexToRow = indexToRow;
  }

  // ----------------------------------------------------------------- instance

  function showError(id) {
    const spinner = byId(`spinner_${id}`);
    if (spinner) {
      spinner.style.display = "none";
    }
    const mapEl = byId(`map_${id}`);
    if (mapEl) {
      clear(mapEl);
      const p = document.createElement("p");
      p.className = "wpgpxmaps_error";
      p.textContent = I18N.mapError || "The map could not be loaded.";
      mapEl.appendChild(p);
    }
  }

  async function initInstance(root) {
    const script = root.querySelector("script.wpgpxmaps-config");
    let cfg;
    try {
      cfg = JSON.parse(script ? script.textContent : "");
    } catch (e) {
      return;
    }
    if (!cfg || cfg.id === undefined) {
      return;
    }

    try {
      await loadGoogle();

      const mapEl = byId(`map_${cfg.id}`);
      if (!mapEl) {
        return;
      }

      const pts = (cfg.map || []).map((p) =>
        Array.isArray(p) && isNumber(p[0]) && isNumber(p[1])
          ? { lat: p[0], lng: p[1] }
          : null,
      );
      const bounds = new google.maps.LatLngBounds();
      pts.forEach((p) => p && bounds.extend(p));

      const mapOptions = {
        center: pts.find(Boolean) || { lat: 0, lng: 0 },
        zoom: 8,
        streetViewControl: false,
        gestureHandling: cfg.scrollWheel ? "greedy" : "cooperative",
      };
      if (SETTINGS.mapId) {
        mapOptions.mapId = SETTINGS.mapId;
      }
      const map = new google.maps.Map(mapEl, mapOptions);

      const tileIds = registerTileLayers(map);
      const googleTypes = ["roadmap", "satellite", "hybrid", "terrain"];
      map.setOptions({
        mapTypeControlOptions: {
          style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
          mapTypeIds: googleTypes.concat(tileIds),
        },
      });
      const wanted = String(cfg.mapType || "HYBRID");
      map.setMapTypeId(
        tileIds.includes(wanted)
          ? wanted
          : googleTypes.includes(wanted.toLowerCase())
            ? wanted.toLowerCase()
            : "hybrid",
      );
      setupOsmFooter(map, cfg.id, tileIds);

      const ctx = {
        cfg,
        map,
        pts,
        bounds,
        chart: null,
        current: null,
        indexToRow: new Map(),
      };
      ctx.setMarker = (point) => {
        if (ctx.current) {
          ctx.current.setPosition(point);
        }
      };
      ctx.moveToLatLng = (lat, lng) => {
        let best = -1;
        let bestDistance = Infinity;
        for (let i = 0; i < pts.length; i++) {
          if (!pts[i]) {
            continue;
          }
          const d = distance2(pts[i], lat, lng);
          if (d < bestDistance) {
            bestDistance = d;
            best = i;
          }
        }
        if (best < 0) {
          return;
        }
        ctx.setMarker(pts[best]);
        const row = ctx.indexToRow.get(best);
        if (ctx.chart && row !== undefined) {
          const points = ctx.chart.series
            .map((s) => s.data[row])
            .filter((p) => p && typeof p.plotX === "number");
          if (points.length) {
            ctx.chart.tooltip.refresh(points);
          }
        }
      };

      drawTrack(ctx);
      drawMarkers(ctx);
      fitToBounds(map, bounds);
      setupControls(ctx);
      setupImages(ctx);
      setupGps(ctx);
      setupChart(ctx);
      mapEl.addEventListener("mouseleave", () => {
        if (ctx.chart && ctx.chart.tooltip) {
          ctx.chart.tooltip.hide(0);
        }
      });

      const spinner = byId(`spinner_${cfg.id}`);
      if (spinner) {
        spinner.style.display = "none";
      }
    } catch (e) {
      showError(cfg.id);
    }
  }

  // -------------------------------------------------------------------- init

  let observer = null;

  function observe(root) {
    if (root.dataset.wpgpxmapsInit) {
      return;
    }
    root.dataset.wpgpxmapsInit = "1";
    if (!("IntersectionObserver" in window)) {
      initInstance(root);
      return;
    }
    if (!observer) {
      observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              observer.unobserve(entry.target);
              initInstance(entry.target);
            }
          });
        },
        { rootMargin: "300px" },
      );
    }
    observer.observe(root);
  }

  function init(scope) {
    (scope || document).querySelectorAll(".wpgpxmaps").forEach(observe);
  }

  window.WPGPXMaps = { init };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => init());
  } else {
    init();
  }
})();
