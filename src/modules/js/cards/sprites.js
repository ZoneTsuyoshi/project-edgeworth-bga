// 自動生成: scripts/build-sprites.mjs（npm run build:sprites）。編集しない。原本を変えたら手元で生成してコミットする。
// 位置の読み方: カード = SPRITES.cards[key].base / .zoom / .effects の { sheet, col, row } と SPRITES.sheets[sheet] の cols・rows・cell・file。
export const SPRITES = {
  "version": 1,
  "zoomDir": "zoom/",
  "options": {
    "quality": 80
  },
  "effects": {
    "crop": {
      "left": 164,
      "top": 820,
      "width": 400,
      "height": 156
    },
    "cell": {
      "w": 300,
      "h": 117
    },
    "adjust": {}
  },
  "sheets": {
    "pe_cards_project_1": {
      "file": "pe_cards_project_1.webp",
      "tier": "base",
      "w": 3300,
      "h": 1257,
      "cols": 11,
      "rows": 3,
      "cell": {
        "w": 300,
        "h": 419
      },
      "lossless": false,
      "count": 33
    },
    "pe_cards_project_2": {
      "file": "pe_cards_project_2.webp",
      "tier": "base",
      "w": 3300,
      "h": 1257,
      "cols": 11,
      "rows": 3,
      "cell": {
        "w": 300,
        "h": 419
      },
      "lossless": false,
      "count": 33
    },
    "pe_cards_project_3": {
      "file": "pe_cards_project_3.webp",
      "tier": "base",
      "w": 3300,
      "h": 1257,
      "cols": 11,
      "rows": 3,
      "cell": {
        "w": 300,
        "h": 419
      },
      "lossless": false,
      "count": 33
    },
    "pe_cards_misc": {
      "file": "pe_cards_misc.webp",
      "tier": "base",
      "w": 1800,
      "h": 1257,
      "cols": 6,
      "rows": 3,
      "cell": {
        "w": 300,
        "h": 419
      },
      "lossless": false,
      "count": 18
    },
    "pe_cards_reward": {
      "file": "pe_cards_reward.webp",
      "tier": "base",
      "w": 2400,
      "h": 1257,
      "cols": 8,
      "rows": 3,
      "cell": {
        "w": 300,
        "h": 419
      },
      "lossless": false,
      "count": 24
    },
    "pe_cards_rocket": {
      "file": "pe_cards_rocket.webp",
      "tier": "base",
      "w": 3200,
      "h": 1430,
      "cols": 8,
      "rows": 5,
      "cell": {
        "w": 400,
        "h": 286
      },
      "lossless": false,
      "count": 40
    },
    "pe_cards_effects": {
      "file": "pe_cards_effects.webp",
      "tier": "base",
      "w": 3900,
      "h": 936,
      "cols": 13,
      "rows": 8,
      "cell": {
        "w": 300,
        "h": 117
      },
      "lossless": false,
      "count": 99
    },
    "pe_tiles": {
      "file": "pe_tiles.webp",
      "tier": "base",
      "w": 1744,
      "h": 1006,
      "cols": 4,
      "rows": 2,
      "cell": {
        "w": 436,
        "h": 503
      },
      "lossless": true,
      "count": 7
    },
    "pe_zoom_project_1": {
      "file": "zoom/pe_zoom_project_1.webp",
      "tier": "zoom",
      "w": 3000,
      "h": 3495,
      "cols": 6,
      "rows": 5,
      "cell": {
        "w": 500,
        "h": 699
      },
      "lossless": false,
      "count": 30
    },
    "pe_zoom_project_2": {
      "file": "zoom/pe_zoom_project_2.webp",
      "tier": "zoom",
      "w": 3000,
      "h": 3495,
      "cols": 6,
      "rows": 5,
      "cell": {
        "w": 500,
        "h": 699
      },
      "lossless": false,
      "count": 30
    },
    "pe_zoom_project_3": {
      "file": "zoom/pe_zoom_project_3.webp",
      "tier": "zoom",
      "w": 3000,
      "h": 3495,
      "cols": 6,
      "rows": 5,
      "cell": {
        "w": 500,
        "h": 699
      },
      "lossless": false,
      "count": 30
    },
    "pe_zoom_misc": {
      "file": "zoom/pe_zoom_misc.webp",
      "tier": "zoom",
      "w": 3000,
      "h": 3495,
      "cols": 6,
      "rows": 5,
      "cell": {
        "w": 500,
        "h": 699
      },
      "lossless": false,
      "count": 27
    },
    "pe_zoom_reward": {
      "file": "zoom/pe_zoom_reward.webp",
      "tier": "zoom",
      "w": 3000,
      "h": 2796,
      "cols": 6,
      "rows": 4,
      "cell": {
        "w": 500,
        "h": 699
      },
      "lossless": false,
      "count": 24
    },
    "pe_zoom_rocket": {
      "file": "zoom/pe_zoom_rocket.webp",
      "tier": "zoom",
      "w": 3600,
      "h": 3010,
      "cols": 6,
      "rows": 7,
      "cell": {
        "w": 600,
        "h": 430
      },
      "lossless": false,
      "count": 40
    }
  },
  "cards": {
    "P001": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 0,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 0
      }
    },
    "P002": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 1,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 0
      }
    },
    "P003": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 2,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 0
      }
    },
    "P004": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 3,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 0
      }
    },
    "P005": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 4,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 0
      }
    },
    "P006": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 5,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 0
      }
    },
    "P007": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 6,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 0,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 0
      }
    },
    "P008": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 7,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 1,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 0
      }
    },
    "P009": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 8,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 2,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 0
      }
    },
    "P010": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 9,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 3,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 0
      }
    },
    "P011": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 10,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 4,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 0
      }
    },
    "P012": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 5,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 0
      }
    },
    "P013": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 0,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 0
      }
    },
    "P014": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 1,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 1
      }
    },
    "P015": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 2,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 1
      }
    },
    "P016": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 3,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 1
      }
    },
    "P017": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 4,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 1
      }
    },
    "P018": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 6,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 5,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 1
      }
    },
    "P019": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 7,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 0,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 1
      }
    },
    "P020": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 8,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 1,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 1
      }
    },
    "P021": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 9,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 2,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 1
      }
    },
    "P022": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 10,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 3,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 1
      }
    },
    "P023": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 4,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 1
      }
    },
    "P024": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 5,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 1
      }
    },
    "P025": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 0,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 1
      }
    },
    "P026": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 1,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 1
      }
    },
    "P027": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 2,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 2
      }
    },
    "P028": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 3,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 2
      }
    },
    "P029": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 6,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 4,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 2
      }
    },
    "P030": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 7,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_1",
        "col": 5,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 2
      }
    },
    "P031": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 8,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 0,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 2
      }
    },
    "P032": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 9,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 1,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 2
      }
    },
    "P033": {
      "base": {
        "sheet": "pe_cards_project_1",
        "col": 10,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 2,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 2
      }
    },
    "P034": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 3,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 2
      }
    },
    "P035": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 4,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 2
      }
    },
    "P036": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 5,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 2
      }
    },
    "P037": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 0,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 2
      }
    },
    "P038": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 1,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 2
      }
    },
    "P039": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 2,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 2
      }
    },
    "P040": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 6,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 3,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 3
      }
    },
    "P041": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 7,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 4,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 3
      }
    },
    "P042": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 8,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 5,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 3
      }
    },
    "P043": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 9,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 0,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 3
      }
    },
    "P044": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 10,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 1,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 3
      }
    },
    "P045": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 2,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 3
      }
    },
    "P046": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 3,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 3
      }
    },
    "P047": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 4,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 3
      }
    },
    "P048": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 5,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 3
      }
    },
    "P049": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 0,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 3
      }
    },
    "P050": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 1,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 3
      }
    },
    "P051": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 6,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 2,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 3
      }
    },
    "P052": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 7,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 3,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 3
      }
    },
    "P053": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 8,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 4,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 4
      }
    },
    "P054": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 9,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 5,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 4
      }
    },
    "P055": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 10,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 0,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 4
      }
    },
    "P056": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 1,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 4
      }
    },
    "P057": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 2,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 4
      }
    },
    "P058": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 3,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 4
      }
    },
    "P059": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 4,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 4
      }
    },
    "P060": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_2",
        "col": 5,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 4
      }
    },
    "P061": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 0,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 4
      }
    },
    "P062": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 6,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 1,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 4
      }
    },
    "P063": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 7,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 2,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 4
      }
    },
    "P064": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 8,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 3,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 4
      }
    },
    "P065": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 9,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 4,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 4
      }
    },
    "P066": {
      "base": {
        "sheet": "pe_cards_project_2",
        "col": 10,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 5,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 5
      }
    },
    "P067": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 0,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 5
      }
    },
    "P068": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 1,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 5
      }
    },
    "P069": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 2,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 5
      }
    },
    "P070": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 3,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 5
      }
    },
    "P071": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 4,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 5
      }
    },
    "P072": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 5,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 5
      }
    },
    "P073": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 6,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 0,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 5
      }
    },
    "P074": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 7,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 1,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 5
      }
    },
    "P075": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 8,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 2,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 5
      }
    },
    "P076": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 9,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 3,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 5
      }
    },
    "P077": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 10,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 4,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 5
      }
    },
    "P078": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 5,
        "row": 2
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 5
      }
    },
    "P079": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 0,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 6
      }
    },
    "P080": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 1,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 6
      }
    },
    "P081": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 2,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 6
      }
    },
    "P082": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 3,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 6
      }
    },
    "P083": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 4,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 6
      }
    },
    "P084": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 6,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 5,
        "row": 3
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 6
      }
    },
    "P085": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 7,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 0,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 6
      }
    },
    "P086": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 8,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 1,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 6
      }
    },
    "P087": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 9,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 2,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 8,
        "row": 6
      }
    },
    "P088": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 10,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 3,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 9,
        "row": 6
      }
    },
    "P089": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 4,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 10,
        "row": 6
      }
    },
    "P090": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_project_3",
        "col": 5,
        "row": 4
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 11,
        "row": 6
      }
    },
    "P091": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 0,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 12,
        "row": 6
      }
    },
    "P092": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 1,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 0,
        "row": 7
      }
    },
    "P093": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 2,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 1,
        "row": 7
      }
    },
    "P094": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 3,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 2,
        "row": 7
      }
    },
    "P095": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 6,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 4,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 3,
        "row": 7
      }
    },
    "P096": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 7,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 5,
        "row": 0
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 4,
        "row": 7
      }
    },
    "P097": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 8,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 0,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 5,
        "row": 7
      }
    },
    "P098": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 9,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 1,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 6,
        "row": 7
      }
    },
    "P099": {
      "base": {
        "sheet": "pe_cards_project_3",
        "col": 10,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 2,
        "row": 1
      },
      "effects": {
        "sheet": "pe_cards_effects",
        "col": 7,
        "row": 7
      }
    },
    "PREV": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 3,
        "row": 1
      }
    },
    "A001": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 4,
        "row": 1
      }
    },
    "A002": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 5,
        "row": 1
      }
    },
    "A003": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 0,
        "row": 2
      }
    },
    "A004": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 1,
        "row": 2
      }
    },
    "A005": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 2,
        "row": 2
      }
    },
    "AREV": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 3,
        "row": 2
      }
    },
    "M001": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 4,
        "row": 2
      }
    },
    "M002": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 5,
        "row": 2
      }
    },
    "M003": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 0,
        "row": 3
      }
    },
    "M004": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 1,
        "row": 3
      }
    },
    "M005": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 2,
        "row": 3
      }
    },
    "M006": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 3,
        "row": 3
      }
    },
    "M007": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 4,
        "row": 3
      }
    },
    "M008": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 5,
        "row": 3
      }
    },
    "M009": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 0,
        "row": 4
      }
    },
    "M010": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 1,
        "row": 4
      }
    },
    "MREV": {
      "base": {
        "sheet": "pe_cards_misc",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_misc",
        "col": 2,
        "row": 4
      }
    },
    "T001": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 0,
        "row": 0
      }
    },
    "T002": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 1,
        "row": 0
      }
    },
    "T003": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 2,
        "row": 0
      }
    },
    "T004": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 3,
        "row": 0
      }
    },
    "T005": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 4,
        "row": 0
      }
    },
    "T006": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 5,
        "row": 0
      }
    },
    "T007": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 6,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 0,
        "row": 1
      }
    },
    "T008": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 7,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 1,
        "row": 1
      }
    },
    "T009": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 2,
        "row": 1
      }
    },
    "T010": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 3,
        "row": 1
      }
    },
    "T011": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 4,
        "row": 1
      }
    },
    "T012": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 5,
        "row": 1
      }
    },
    "T013": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 0,
        "row": 2
      }
    },
    "T014": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 1,
        "row": 2
      }
    },
    "T015": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 6,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 2,
        "row": 2
      }
    },
    "T016": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 7,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 3,
        "row": 2
      }
    },
    "T017": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 4,
        "row": 2
      }
    },
    "T018": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 5,
        "row": 2
      }
    },
    "T019": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 0,
        "row": 3
      }
    },
    "T020": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 1,
        "row": 3
      }
    },
    "T021": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 2,
        "row": 3
      }
    },
    "T022": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 3,
        "row": 3
      }
    },
    "T023": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 6,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 4,
        "row": 3
      }
    },
    "T024": {
      "base": {
        "sheet": "pe_cards_reward",
        "col": 7,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_reward",
        "col": 5,
        "row": 3
      }
    },
    "R001B": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 0,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 0
      }
    },
    "R001G": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 1,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 0
      }
    },
    "R001R": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 2,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 0
      }
    },
    "R001Y": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 3,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 0
      }
    },
    "R002B": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 4,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 0
      }
    },
    "R002G": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 5,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 0
      }
    },
    "R002R": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 6,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 1
      }
    },
    "R002Y": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 7,
        "row": 0
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 1
      }
    },
    "R003B": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 0,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 1
      }
    },
    "R003G": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 1,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 1
      }
    },
    "R003R": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 2,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 1
      }
    },
    "R003Y": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 3,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 1
      }
    },
    "R004B": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 4,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 2
      }
    },
    "R004G": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 5,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 2
      }
    },
    "R004R": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 6,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 2
      }
    },
    "R004Y": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 7,
        "row": 1
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 2
      }
    },
    "R005B": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 0,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 2
      }
    },
    "R005G": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 1,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 2
      }
    },
    "R005R": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 2,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 3
      }
    },
    "R005Y": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 3,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 3
      }
    },
    "R006": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 4,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 3
      }
    },
    "R007": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 5,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 3
      }
    },
    "R008": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 6,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 3
      }
    },
    "R009": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 7,
        "row": 2
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 3
      }
    },
    "R010": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 0,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 4
      }
    },
    "R011": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 1,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 4
      }
    },
    "R012": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 2,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 4
      }
    },
    "R013": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 3,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 4
      }
    },
    "R014": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 4,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 4
      }
    },
    "R015": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 5,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 4
      }
    },
    "R016": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 6,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 5
      }
    },
    "R017": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 7,
        "row": 3
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 5
      }
    },
    "R018": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 0,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 5
      }
    },
    "R019": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 1,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 5
      }
    },
    "R020": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 2,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 4,
        "row": 5
      }
    },
    "R021": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 3,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 5,
        "row": 5
      }
    },
    "R022": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 4,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 0,
        "row": 6
      }
    },
    "R023": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 5,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 1,
        "row": 6
      }
    },
    "R024": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 6,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 2,
        "row": 6
      }
    },
    "R025": {
      "base": {
        "sheet": "pe_cards_rocket",
        "col": 7,
        "row": 4
      },
      "zoom": {
        "sheet": "pe_zoom_rocket",
        "col": 3,
        "row": 6
      }
    }
  },
  "tiles": {
    "sea": {
      "sheet": "pe_tiles",
      "col": 0,
      "row": 0
    },
    "land": {
      "sheet": "pe_tiles",
      "col": 1,
      "row": 0
    },
    "deep_sea": {
      "sheet": "pe_tiles",
      "col": 2,
      "row": 0
    },
    "reef": {
      "sheet": "pe_tiles",
      "col": 3,
      "row": 0
    },
    "forest": {
      "sheet": "pe_tiles",
      "col": 0,
      "row": 1
    },
    "mountain": {
      "sheet": "pe_tiles",
      "col": 1,
      "row": 1
    },
    "snow": {
      "sheet": "pe_tiles",
      "col": 2,
      "row": 1
    }
  },
  "pieces": {
    "sheet": {
      "file": "pe_pieces.webp",
      "w": 928,
      "h": 1716
    },
    "items": {
      "satellite_hammer_b": {
        "cell": {
          "x": 0,
          "y": 0,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 3,
          "y": 7,
          "w": 225,
          "h": 117
        }
      },
      "satellite_hammer_g": {
        "cell": {
          "x": 232,
          "y": 0,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 235,
          "y": 7,
          "w": 225,
          "h": 117
        }
      },
      "satellite_hammer_r": {
        "cell": {
          "x": 464,
          "y": 0,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 467,
          "y": 7,
          "w": 226,
          "h": 117
        }
      },
      "satellite_hammer_y": {
        "cell": {
          "x": 696,
          "y": 0,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 699,
          "y": 7,
          "w": 226,
          "h": 117
        }
      },
      "satellite_retro_b": {
        "cell": {
          "x": 0,
          "y": 132,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 8,
          "y": 136,
          "w": 216,
          "h": 123
        }
      },
      "satellite_retro_g": {
        "cell": {
          "x": 232,
          "y": 132,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 240,
          "y": 136,
          "w": 216,
          "h": 123
        }
      },
      "satellite_retro_r": {
        "cell": {
          "x": 464,
          "y": 132,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 472,
          "y": 136,
          "w": 216,
          "h": 123
        }
      },
      "satellite_retro_y": {
        "cell": {
          "x": 696,
          "y": 132,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 704,
          "y": 136,
          "w": 216,
          "h": 123
        }
      },
      "satellite_scallop_b": {
        "cell": {
          "x": 0,
          "y": 264,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 2,
          "y": 268,
          "w": 228,
          "h": 124
        }
      },
      "satellite_scallop_g": {
        "cell": {
          "x": 232,
          "y": 264,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 234,
          "y": 268,
          "w": 228,
          "h": 124
        }
      },
      "satellite_scallop_r": {
        "cell": {
          "x": 464,
          "y": 264,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 466,
          "y": 268,
          "w": 228,
          "h": 124
        }
      },
      "satellite_scallop_y": {
        "cell": {
          "x": 696,
          "y": 264,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 698,
          "y": 268,
          "w": 228,
          "h": 124
        }
      },
      "satellite_concorde_b": {
        "cell": {
          "x": 0,
          "y": 396,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 1,
          "y": 397,
          "w": 229,
          "h": 130
        }
      },
      "satellite_concorde_g": {
        "cell": {
          "x": 232,
          "y": 396,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 233,
          "y": 397,
          "w": 229,
          "h": 130
        }
      },
      "satellite_concorde_r": {
        "cell": {
          "x": 464,
          "y": 396,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 466,
          "y": 397,
          "w": 228,
          "h": 130
        }
      },
      "satellite_concorde_y": {
        "cell": {
          "x": 696,
          "y": 396,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 698,
          "y": 397,
          "w": 228,
          "h": 130
        }
      },
      "satellite_stealth_b": {
        "cell": {
          "x": 0,
          "y": 528,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 1,
          "y": 534,
          "w": 229,
          "h": 119
        }
      },
      "satellite_stealth_g": {
        "cell": {
          "x": 232,
          "y": 528,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 233,
          "y": 534,
          "w": 229,
          "h": 119
        }
      },
      "satellite_stealth_r": {
        "cell": {
          "x": 464,
          "y": 528,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 466,
          "y": 534,
          "w": 228,
          "h": 119
        }
      },
      "satellite_stealth_y": {
        "cell": {
          "x": 696,
          "y": 528,
          "w": 232,
          "h": 132
        },
        "image": {
          "x": 697,
          "y": 534,
          "w": 229,
          "h": 119
        }
      },
      "base_b": {
        "cell": {
          "x": 0,
          "y": 660,
          "w": 164,
          "h": 228
        },
        "image": {
          "x": 0,
          "y": 660,
          "w": 163,
          "h": 228
        }
      },
      "base_g": {
        "cell": {
          "x": 164,
          "y": 660,
          "w": 164,
          "h": 228
        },
        "image": {
          "x": 164,
          "y": 660,
          "w": 163,
          "h": 228
        }
      },
      "base_r": {
        "cell": {
          "x": 328,
          "y": 660,
          "w": 164,
          "h": 228
        },
        "image": {
          "x": 329,
          "y": 660,
          "w": 162,
          "h": 228
        }
      },
      "base_y": {
        "cell": {
          "x": 492,
          "y": 660,
          "w": 164,
          "h": 228
        },
        "image": {
          "x": 492,
          "y": 660,
          "w": 163,
          "h": 228
        }
      },
      "fuel_b": {
        "cell": {
          "x": 0,
          "y": 888,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 1,
          "y": 888,
          "w": 78,
          "h": 95
        }
      },
      "fuel_g": {
        "cell": {
          "x": 80,
          "y": 888,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 81,
          "y": 888,
          "w": 77,
          "h": 95
        }
      },
      "fuel_r": {
        "cell": {
          "x": 160,
          "y": 888,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 161,
          "y": 888,
          "w": 78,
          "h": 95
        }
      },
      "fuel_y": {
        "cell": {
          "x": 240,
          "y": 888,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 241,
          "y": 888,
          "w": 77,
          "h": 95
        }
      },
      "disk_b": {
        "cell": {
          "x": 0,
          "y": 984,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 8,
          "y": 1014,
          "w": 64,
          "h": 35
        }
      },
      "disk_g": {
        "cell": {
          "x": 80,
          "y": 984,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 88,
          "y": 1014,
          "w": 63,
          "h": 35
        }
      },
      "disk_r": {
        "cell": {
          "x": 160,
          "y": 984,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 168,
          "y": 1014,
          "w": 63,
          "h": 35
        }
      },
      "disk_y": {
        "cell": {
          "x": 240,
          "y": 984,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 248,
          "y": 1014,
          "w": 63,
          "h": 35
        }
      },
      "cube_b": {
        "cell": {
          "x": 0,
          "y": 1080,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 16,
          "y": 1100,
          "w": 48,
          "h": 56
        }
      },
      "cube_g": {
        "cell": {
          "x": 80,
          "y": 1080,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 95,
          "y": 1100,
          "w": 49,
          "h": 56
        }
      },
      "cube_r": {
        "cell": {
          "x": 160,
          "y": 1080,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 175,
          "y": 1100,
          "w": 49,
          "h": 56
        }
      },
      "cube_y": {
        "cell": {
          "x": 240,
          "y": 1080,
          "w": 80,
          "h": 96
        },
        "image": {
          "x": 256,
          "y": 1100,
          "w": 48,
          "h": 56
        }
      },
      "maya_1": {
        "cell": {
          "x": 0,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 0,
          "y": 1177,
          "w": 127,
          "h": 130
        }
      },
      "maya_3": {
        "cell": {
          "x": 128,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 129,
          "y": 1177,
          "w": 125,
          "h": 129
        }
      },
      "maya_5": {
        "cell": {
          "x": 256,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 257,
          "y": 1177,
          "w": 126,
          "h": 129
        }
      },
      "maya_10": {
        "cell": {
          "x": 384,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 385,
          "y": 1177,
          "w": 125,
          "h": 129
        }
      },
      "maya_20": {
        "cell": {
          "x": 512,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 513,
          "y": 1177,
          "w": 125,
          "h": 129
        }
      },
      "maya_50": {
        "cell": {
          "x": 640,
          "y": 1176,
          "w": 128,
          "h": 132
        },
        "image": {
          "x": 641,
          "y": 1177,
          "w": 126,
          "h": 129
        }
      },
      "area_A": {
        "cell": {
          "x": 0,
          "y": 1308,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 6,
          "y": 1324,
          "w": 116,
          "h": 108
        }
      },
      "area_B": {
        "cell": {
          "x": 128,
          "y": 1308,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 143,
          "y": 1309,
          "w": 98,
          "h": 138
        }
      },
      "area_C": {
        "cell": {
          "x": 256,
          "y": 1308,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 257,
          "y": 1321,
          "w": 126,
          "h": 114
        }
      },
      "area_D": {
        "cell": {
          "x": 384,
          "y": 1308,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 396,
          "y": 1319,
          "w": 103,
          "h": 118
        }
      },
      "area_E": {
        "cell": {
          "x": 0,
          "y": 1448,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 16,
          "y": 1466,
          "w": 95,
          "h": 103
        }
      },
      "area_F": {
        "cell": {
          "x": 128,
          "y": 1448,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 144,
          "y": 1465,
          "w": 96,
          "h": 105
        }
      },
      "area_G": {
        "cell": {
          "x": 256,
          "y": 1448,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 266,
          "y": 1464,
          "w": 108,
          "h": 108
        }
      },
      "area_H": {
        "cell": {
          "x": 384,
          "y": 1448,
          "w": 128,
          "h": 140
        },
        "image": {
          "x": 397,
          "y": 1466,
          "w": 102,
          "h": 104
        }
      },
      "icon_maya": {
        "cell": {
          "x": 0,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 0,
          "y": 1588,
          "w": 64,
          "h": 64
        }
      },
      "icon_budget": {
        "cell": {
          "x": 64,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 64,
          "y": 1588,
          "w": 64,
          "h": 63
        }
      },
      "icon_fuel": {
        "cell": {
          "x": 128,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 137,
          "y": 1588,
          "w": 46,
          "h": 64
        }
      },
      "icon_crew": {
        "cell": {
          "x": 192,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 193,
          "y": 1588,
          "w": 61,
          "h": 64
        }
      },
      "icon_device": {
        "cell": {
          "x": 256,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 256,
          "y": 1588,
          "w": 63,
          "h": 64
        }
      },
      "icon_software": {
        "cell": {
          "x": 320,
          "y": 1588,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 320,
          "y": 1588,
          "w": 64,
          "h": 64
        }
      },
      "icon_land": {
        "cell": {
          "x": 0,
          "y": 1652,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 4,
          "y": 1652,
          "w": 55,
          "h": 64
        }
      },
      "icon_sea": {
        "cell": {
          "x": 64,
          "y": 1652,
          "w": 64,
          "h": 64
        },
        "image": {
          "x": 68,
          "y": 1652,
          "w": 55,
          "h": 64
        }
      }
    }
  }
};
