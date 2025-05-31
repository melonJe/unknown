let dice = {
  top: "red",
  bottom: "blue",
  left: "green",
  right: "yellow",
  front: "white",
  back: "black"
};

function roll(direction) {
  let { top, bottom, left, right, front, back } = dice;
  switch (direction) {
    case 'right':
      dice = { top: left, bottom: right, left: bottom, right: top, front, back };
      break;
    case 'left':
      dice = { top: right, bottom: left, left: top, right: bottom, front, back };
      break;
    case 'up':
      dice = { top: front, bottom: back, front: bottom, back: top, left, right };
      break;
    case 'down':
      dice = { top: back, bottom: front, front: top, back: bottom, left, right };
      break;
  }
}
