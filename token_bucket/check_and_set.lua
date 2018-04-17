local function mysplit(inputstr, sep)
    if sep == nil then
            sep = "%s"
    end
    local t={} ; 
    local i=1
    for str in string.gmatch(inputstr, "([^"..sep.."]+)") do
            t[i] = str
            i = i + 1
    end
    return t
end

local res = redis.pcall('get',KEYS[1])

if (res == false) then
    -- bucket不存在，初始化为capacity - 1
    local str = string.format("%d %d",ARGV[1] - 1,ARGV[2])  
    return redis.pcall('set',KEYS[1],str)
end

local arr_str = mysplit(res, " ")

local num = arr_str[1]
local timestamp = arr_str[2]

-- 根据时间戳来计算num，这里0.5秒一个token
num = num + (ARGV[2] - timestamp) / ARGV[3]
-- 不超过10个令牌
if num > 10 then
    num = 10
end

if (tonumber(num) > 0) then
    -- 拿到token并减一
    local str = string.format("%d %d",num - 1,ARGV[2])
    return redis.pcall('set', KEYS[1], str)
else 
    -- 没有拿到token
    return false
end

