#!/bin/bash
input=$(cat)
STATE_FILE="$HOME/.claude/chat_token_state.json"

MODEL=$(echo "$input" | jq -r '.model.display_name')
USED=$(echo "$input" | jq -r '.context_window.used_percentage // 0' | cut -d. -f1)
COST=$(echo "$input" | jq -r '.cost.total_cost_usd // 0')
WEEK_PCT=$(echo "$input" | jq -r '.rate_limits.seven_day.used_percentage // empty')
SESSION_PCT=$(echo "$input" | jq -r '.rate_limits.five_hour.used_percentage // empty')
IN_TOK=$(echo "$input" | jq -r '.context_window.total_input_tokens // 0')
OUT_TOK=$(echo "$input" | jq -r '.context_window.total_output_tokens // 0')

# Load or initialize per-chat state
if [ -f "$STATE_FILE" ]; then
    LAST_USED=$(jq -r '.last_used_pct // 0' "$STATE_FILE")
    BASELINE_IN=$(jq -r '.baseline_in // 0' "$STATE_FILE")
    BASELINE_OUT=$(jq -r '.baseline_out // 0' "$STATE_FILE")
else
    LAST_USED=0; BASELINE_IN=0; BASELINE_OUT=0
fi

# Detect /clear: context dropped back to 0 after being non-zero
if [ "$USED" = "0" ] && [ "$LAST_USED" -gt 0 ]; then
    BASELINE_IN=$IN_TOK
    BASELINE_OUT=$OUT_TOK
fi

# Chat tokens = current minus what was there at last clear
CHAT_IN=$((IN_TOK - BASELINE_IN))
CHAT_OUT=$((OUT_TOK - BASELINE_OUT))
CHAT_TOTAL=$((CHAT_IN + CHAT_OUT))

# Persist state
jq -n \
    --argjson lu "$USED" \
    --argjson bi "$BASELINE_IN" \
    --argjson bo "$BASELINE_OUT" \
    '{last_used_pct: $lu, baseline_in: $bi, baseline_out: $bo}' > "$STATE_FILE"

humanize() {
    local n=$1
    if [ "$n" -ge 1000 ]; then
        printf "%.0fK" "$(echo "scale=1; $n / 1000" | bc)"
    else
        printf "%d" "$n"
    fi
}

LIMIT_PART=""
if [ -n "$SESSION_PCT" ]; then
    FIVE_RESETS_AT=$(echo "$input" | jq -r '.rate_limits.five_hour.resets_at // empty')
    if [ -n "$FIVE_RESETS_AT" ]; then
        RESET_TIME=$(TZ="America/New_York" date -r "$FIVE_RESETS_AT" +"%l:%M %p" | sed 's/^ //')
        LIMIT_PART=" | ${RESET_TIME}: $(printf '%.0f' "$SESSION_PCT")%"
    else
        LIMIT_PART=" | 5h: $(printf '%.0f' "$SESSION_PCT")% used"
    fi
fi
if [ -n "$WEEK_PCT" ]; then
    LIMIT_PART="$LIMIT_PART | Week: $(printf '%.0f' "$WEEK_PCT")%"
fi

printf "[%s] Chat: %s | ↑ %s ↓ %s | $%.2f%s" \
    "$MODEL" "$(humanize $CHAT_TOTAL)" "$(humanize $CHAT_IN)" "$(humanize $CHAT_OUT)" "$COST" "$LIMIT_PART"